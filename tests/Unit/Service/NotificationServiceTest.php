<?php

namespace HiPay\Payment\Tests\Unit\Service;

use HiPay\Fullservice\Enum\Transaction\TransactionStatus;
use HiPay\Fullservice\Exception\ApiErrorException;
use HiPay\Fullservice\Exception\UnexpectedValueException;
use HiPay\Payment\Core\Checkout\Payment\Capture\OrderCaptureCollection;
use HiPay\Payment\Core\Checkout\Payment\Capture\OrderCaptureEntity;
use HiPay\Payment\Core\Checkout\Payment\HipayNotification\HipayNotificationCollection;
use HiPay\Payment\Core\Checkout\Payment\HipayNotification\HipayNotificationEntity;
use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderCollection;
use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderEntity;
use HiPay\Payment\Core\Checkout\Payment\HipayStatusFlow\HipayStatusFlowEntity;
use HiPay\Payment\Core\Checkout\Payment\Refund\OrderRefundCollection;
use HiPay\Payment\Core\Checkout\Payment\Refund\OrderRefundEntity;
use HiPay\Payment\Enum\CaptureStatus;
use HiPay\Payment\Enum\RefundStatus;
use HiPay\Payment\Service\NotificationService;
use HiPay\Payment\Tests\Tools\ReadHipayConfigServiceMockTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;

class NotificationServiceTest extends TestCase
{
    use ReadHipayConfigServiceMockTrait;

    private function addSignature(Request $request, string $algo, string $passphrase)
    {
        $request->headers->add([
            'x-allopass-signature' => hash($algo, $request->getContent() . $passphrase),
        ]);

        return $request;
    }

    public static function provideSaveNotificationRequest()
    {
        return [
            // FAILED
            [TransactionStatus::AUTHENTICATION_FAILED, NotificationService::FAILED],
            [TransactionStatus::BLOCKED, NotificationService::FAILED],
            [TransactionStatus::DENIED, NotificationService::FAILED],
            [TransactionStatus::REFUSED, NotificationService::FAILED],
            [TransactionStatus::EXPIRED, NotificationService::FAILED],
            [134, NotificationService::FAILED],
            [178, NotificationService::FAILED],
            // CANCELLED
            [TransactionStatus::CANCELLED, NotificationService::CANCELLED],
            [143, NotificationService::CANCELLED],
            [TransactionStatus::AUTHORIZATION_CANCELLATION_REQUESTED, NotificationService::CANCELLED],
            // IN PROGRESS
            [TransactionStatus::AUTHORIZED_AND_PENDING, NotificationService::PROCESS],
            [TransactionStatus::AUTHORIZATION_REQUESTED, NotificationService::PROCESS],
            [144, NotificationService::PROCESS],
            [169, NotificationService::PROCESS],
            [172, NotificationService::PROCESS],
            [174, NotificationService::PROCESS],
            [177, NotificationService::PROCESS],
            [200, NotificationService::PROCESS],
            // CHARGEDBACK
            [TransactionStatus::CHARGED_BACK, NotificationService::CHARGEDBACK],
            // AUTHORIZED
            [TransactionStatus::AUTHORIZED, NotificationService::AUTHORIZE],
            // PROCESS_AFTER_AUTHORIZED
            [TransactionStatus::CAPTURE_REFUSED, NotificationService::PROCESS_AFTER_AUTHORIZE],
            [TransactionStatus::CAPTURE_REQUESTED, NotificationService::PROCESS_AFTER_AUTHORIZE],
            // Proccess AFTER CAPTURE
            [TransactionStatus::REFUND_REQUESTED, NotificationService::PROCESS_AFTER_CAPTURE],
            [TransactionStatus::REFUND_REFUSED, NotificationService::PROCESS_AFTER_CAPTURE],
            // PAID PARTIALLY
            [TransactionStatus::PARTIALLY_CAPTURED, NotificationService::PAY_PARTIALLY],
            // PAID
            [TransactionStatus::CAPTURED, NotificationService::PAID],
            [166, NotificationService::PAID],
            [168, NotificationService::PAID],
            // REFUNDED PARTIALLY
            [TransactionStatus::PARTIALLY_REFUNDED, NotificationService::REFUNDED_PARTIALLY],
            // REFUNDED
            [TransactionStatus::REFUNDED, NotificationService::REFUNDED],
        ];
    }

    #[DataProvider('provideSaveNotificationRequest')]
    public function testSaveNotificationRequest($code, $codeEntity)
    {
        $hipayNotification = [];
        $transaction = $this->generateTransaction();
        $hipayOrder = $this->generateHipayOrder($transaction);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationRepo->method('create')->willReturnCallback(function ($arg) use (&$hipayNotification) {
            $hipayNotification = $arg;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);

        $i = 0;
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder, &$i) {
            $collection = new HipayOrderCollection([]);
            $collection2 = new HipayOrderCollection([$hipayOrder]);
            $return = [
                new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context),
                new EntitySearchResult(HipayOrderEntity::class, $collection2->count(), $collection2, null, $criteria, $context),
            ];

            if (!array_key_exists($i, $return)) {
                throw new \AssertionError('Expected ' . count($return) . ' calls. Actual is ' . $i);
            }

            return $return[$i++];
        });

        $transactionReference = random_int(0, PHP_INT_MAX);
        $hipayOrderRepo->expects($this->once())->method('create')->willReturnCallback(function ($entities) use ($hipayOrder, $transactionReference) {
            $this->assertEquals(
                [
                    array_merge($hipayOrder->toArray(), [
                        'transactionReference' => $transactionReference,
                        'id' => null,
                        '_uniqueIdentifier' => null,
                    ]),
                ],
                $entities
            );

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $config = $this->getReadHipayConfig([
            'hashStage' => 'sha256',
            'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
            'environment' => 'Stage',
        ]);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(LoggerInterface::class)
        );

        $content = [
            'status' => $code,
            'date_updated' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:sO'),
            'transaction_reference' => $transactionReference,
            'order' => [
                'id' => 'ORDER_ID',
            ],
            'custom_data' => ['transaction_id' => md5(random_int(0, PHP_INT_MAX))],
        ];
        $request = new Request([], $content);

        $service->saveNotificationRequest(
            $this->addSignature($request, $config->getHash(), $config->getPassphrase()),
            $this->createMock(Context::class)
        );

        $this->assertEquals($codeEntity, $hipayNotification[0]['status'], 'bad status');
        $this->assertEquals($request->request->all(), $hipayNotification[0]['data'], 'bad data');
        $this->assertEquals((new \DateTimeImmutable($content['date_updated']))->format('Y-m-d\TH:i:s.000P'), $hipayNotification[0]['notificationUpdatedAt'], 'bad notification date');
        $this->assertEquals('HIPAY_ID', $hipayNotification[0]['hipayOrderId'], 'bad hipayOrderId');
        $this->assertEquals(['id' => 'HIPAY_ID'], $hipayNotification[0]['hipayOrder'], 'bad hipayOrder data');
    }

    #[DataProvider('provideSaveNotificationRequest')]
    public function testSaveNotificationOnExistingHipayOrderRequest($code, $codeEntity)
    {
        $hipayNotification = [];
        $transaction = $this->generateTransaction();
        $hipayOrder = $this->generateHipayOrder($transaction);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationRepo->method('create')->willReturnCallback(function ($arg) use (&$hipayNotification) {
            $hipayNotification = $arg;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $transactionReference = random_int(0, PHP_INT_MAX);
        $hipayOrderRepo->expects($this->once())->method('update')->willReturnCallback(function ($entities) use ($hipayOrder, $transactionReference) {
            $this->assertEquals(
                [
                    array_merge($hipayOrder->toArray(), [
                        'transactionReference' => $transactionReference,
                    ]),
                ],
                $entities
            );

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $config = $this->getReadHipayConfig([
            'hashStage' => 'sha256',
            'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
            'environment' => 'Stage',
        ]);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(LoggerInterface::class)
        );

        $content = [
            'status' => $code,
            'date_updated' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:sO'),
            'transaction_reference' => $transactionReference,
            'order' => [
                'id' => 'ORDER_ID',
            ],
            'custom_data' => ['transaction_id' => md5(random_int(0, PHP_INT_MAX))],
        ];
        $request = new Request([], $content);

        $service->saveNotificationRequest(
            $this->addSignature($request, $config->getHash(), $config->getPassphrase()),
            $this->createMock(Context::class)
        );

        $this->assertEquals($codeEntity, $hipayNotification[0]['status'], 'bad status');
        $this->assertEquals($request->request->all(), $hipayNotification[0]['data'], 'bad data');
        $this->assertEquals((new \DateTimeImmutable($content['date_updated']))->format('Y-m-d\TH:i:s.000P'), $hipayNotification[0]['notificationUpdatedAt'], 'bad notification date');
        $this->assertEquals('HIPAY_ID', $hipayNotification[0]['hipayOrderId'], 'bad hipayOrderId');
        $this->assertEquals(['id' => 'HIPAY_ID'], $hipayNotification[0]['hipayOrder'], 'bad hipayOrder data');
    }

    public function testSaveNotificationRequestBadAlgo()
    {
        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);

        $config = $this->getReadHipayConfig([
            'hashStage' => 'TEST',
            'environment' => 'Stage',
        ]);

        $service = new NotificationService(
            $this->createMock(EntityRepository::class),
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(LoggerInterface::class)
        );

        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage('Bad configuration unknown algorythm "TEST"');

        $service->saveNotificationRequest(new Request(),
            $this->createMock(Context::class));
    }

    public function testSaveNotificationRequestSignatureNotFound()
    {
        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);

        $config = $this->getReadHipayConfig([
            'hashStage' => 'sha256',
            'environment' => 'Stage',
        ]);

        $service = new NotificationService(
            $this->createMock(EntityRepository::class),
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(LoggerInterface::class)
        );

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('Missing signature header');

        $service->saveNotificationRequest(new Request(),
            $this->createMock(Context::class));
    }

    public function testSaveNotificationRequestSignatureInvalid()
    {
        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);

        $config = $this->getReadHipayConfig([
            'hashStage' => 'sha256',
            'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
            'environment' => 'Stage',
        ]);

        $service = new NotificationService(
            $this->createMock(EntityRepository::class),
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(LoggerInterface::class)
        );

        $request = new Request();
        $request->headers->add([
            'x-allopass-signature' => 'badSignature',
        ]);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Signature does not match');

        $service->saveNotificationRequest($request,
            $this->createMock(Context::class));
    }

    public function testSaveNotificationRequestDateUpdatedMissing()
    {
        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);

        $config = $this->getReadHipayConfig([
            'hashStage' => 'sha256',
            'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
            'environment' => 'Stage',
        ]);

        $service = new NotificationService(
            $this->createMock(EntityRepository::class),
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(LoggerInterface::class)
        );

        $request = new Request([], ['foo' => 'bar']);

        $this->expectException(MissingMandatoryParametersException::class);
        $this->expectExceptionMessage('date_updated is mandatory');

        $service->saveNotificationRequest(
            $this->addSignature($request, $config->getHash(), $config->getPassphrase()),
            $this->createMock(Context::class)
        );
    }

    public function testSaveNotificationRequesTransactionIdMissing()
    {
        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);

        $config = $this->getReadHipayConfig([
            'hashStage' => 'sha256',
            'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
            'environment' => 'Stage',
        ]);

        $service = new NotificationService(
            $this->createMock(EntityRepository::class),
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(LoggerInterface::class)
        );

        $content = [
            'date_updated' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:sO'),
        ];
        $request = new Request([], $content);

        $this->expectException(MissingMandatoryParametersException::class);
        $this->expectExceptionMessage('custom_data.transaction_id is mandatory');

        $service->saveNotificationRequest(
            $this->addSignature($request, $config->getHash(), $config->getPassphrase()),
            $this->createMock(Context::class)
        );
    }

    public function testSaveNotificationRequestTransactionNotFound()
    {
        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);

        $config = $this->getReadHipayConfig([
            'hashStage' => 'sha256',
            'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
            'environment' => 'Stage',
        ]);

        $service = new NotificationService(
            $this->createMock(EntityRepository::class),
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(LoggerInterface::class)
        );

        $content = [
            'status' => 0,
            'date_updated' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:sO'),
            'order' => ['id' => 'ORDER_ID'],
            'transaction_reference' => random_int(0, PHP_INT_MAX),
            'custom_data' => ['transaction_id' => md5(random_int(0, PHP_INT_MAX))],
        ];
        $request = new Request([], $content);

        $this->expectExceptionMessage('Transaction ' . $content['custom_data']['transaction_id'] . ' is not found');
        $this->expectException(NotFoundResourceException::class);

        $service->saveNotificationRequest(
            $this->addSignature($request, $config->getHash(), $config->getPassphrase()),
            $this->createMock(Context::class)
        );
    }

    public function testSaveNotificationRequestTransactionReferenceMissing()
    {
        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);

        $config = $this->getReadHipayConfig([
            'hashStage' => 'sha256',
            'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
            'environment' => 'Stage',
        ]);

        $service = new NotificationService(
            $this->createMock(EntityRepository::class),
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(LoggerInterface::class)
        );

        $content = [
            'status' => 0,
            'date_updated' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:sO'),
            'order' => ['id' => 'ORDER_ID'],
            'custom_data' => ['transaction_id' => md5(random_int(0, PHP_INT_MAX))],
        ];
        $request = new Request([], $content);
        $this->expectExceptionMessage('transaction_reference is mandatory');
        $this->expectException(MissingMandatoryParametersException::class);

        $service->saveNotificationRequest(
            $this->addSignature($request, $config->getHash(), $config->getPassphrase()),
            $this->createMock(Context::class)
        );
    }

    public function testSaveNotificationRequestInvalidStatus()
    {
        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);

        $transaction = $this->generateTransaction();

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $config = $this->getReadHipayConfig([
            'hashStage' => 'sha256',
            'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
            'environment' => 'Stage',
        ]);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(LoggerInterface::class)
        );

        $content = [
            'status' => 0,
            'date_updated' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:sO'),
            'order' => ['id' => 'ORDER_ID'],
            'transaction_reference' => random_int(0, PHP_INT_MAX),
            'custom_data' => ['transaction_id' => md5(random_int(0, PHP_INT_MAX))],
        ];
        $request = new Request([], $content);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Status code "0" invalid');

        $service->saveNotificationRequest(
            $this->addSignature($request, $config->getHash(), $config->getPassphrase()),
            $this->createMock(Context::class)
        );
    }

    private function generateTransaction(string $initialState = OrderTransactionStates::STATE_IN_PROGRESS): OrderTransactionEntity
    {
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setCustomerId('CUSTOMER_ID');

        $order = new OrderEntity();
        $order->setId('ORDER_ID');
        $order->setVersionId('ORDER_VERSION_ID');
        $order->setOrderCustomer($orderCustomer);

        $stateMachine = new StateMachineStateEntity();
        $stateMachine->setTechnicalName($initialState);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('TRX_ID');
        $transaction->setVersionId('TRX_VERSION_ID');
        $transaction->setOrder($order);
        $transaction->setOrderId($order->getId());
        $transaction->setStateMachineState($stateMachine);

        return $transaction;
    }

    private function generateHipayOrder(OrderTransactionEntity $transaction, array $previousHipayStatus = null): HipayOrderEntity
    {
        if (!$transaction) {
            $transaction = $this->generateTransaction();
        }

        $hipayOrder = new HipayOrderEntity();
        $hipayOrder->setId('HIPAY_ID');
        $hipayOrder->setOrder($transaction->getOrder());
        $hipayOrder->setTransaction($transaction);
        $hipayOrder->setTransactionReference(md5(random_int(0, PHP_INT_MAX)));

        if ($previousHipayStatus) {
            foreach ($previousHipayStatus as $status) {
                $statusFlow = new HipayStatusFlowEntity();
                $statusFlow->setCode($status);
                $statusFlow->setUniqueIdentifier(md5(random_int(0, PHP_INT_MAX)));
                $statusFlow->setHash(dechex(crc32($statusFlow->getUniqueIdentifier())));
                $hipayOrder->addStatusFlow($statusFlow);
            }
        }

        return $hipayOrder;
    }

    public static function provideTestDispatchNotification()
    {
        return [
            [NotificationService::PROCESS, TransactionStatus::AUTHORIZED_AND_PENDING, OrderTransactionStates::STATE_IN_PROGRESS, 'process', OrderTransactionStates::STATE_IN_PROGRESS],
            [NotificationService::FAILED, TransactionStatus::AUTHENTICATION_FAILED, OrderTransactionStates::STATE_FAILED, 'fail', OrderTransactionStates::STATE_FAILED],
            [NotificationService::CHARGEDBACK, TransactionStatus::CHARGED_BACK, OrderTransactionStates::STATE_CHARGEBACK, 'chargeback', OrderTransactionStates::STATE_CHARGEBACK],
            [NotificationService::AUTHORIZE, TransactionStatus::AUTHORIZED, OrderTransactionStates::STATE_AUTHORIZED, 'authorize', OrderTransactionStates::STATE_AUTHORIZED],
            [NotificationService::CANCELLED, TransactionStatus::CANCELLED, OrderTransactionStates::STATE_CANCELLED, 'cancel', OrderTransactionStates::STATE_CANCELLED],
        ];
    }

    #[DataProvider('provideTestDispatchNotification')]
    public function testDispatchExpiredNotification($notificationStatus, $hipayStatus, $initialState, $methodExpected, $expectedState)
    {
        $transaction = $this->generateTransaction();
        $hipayOrder = $this->generateHipayOrder($transaction);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus($notificationStatus);
        $entity->setCreatedAt(new \DateTime('yesterday'));
        $entity->setData([
            'captured_amount' => random_int(0, PHP_INT_MAX),
            'refunded_amount' => random_int(0, PHP_INT_MAX),
            'status' => $hipayStatus,
            'operation' => [
                'id' => md5(random_int(0, PHP_INT_MAX)),
            ],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        /** @var Criteria|null $notificationCriteria */
        $notificationCriteria = null;
        $notificationRepo->method('search')->willReturnCallback(function ($crit, $context) use (&$notificationCriteria, $notificationCollection) {
            $notificationCriteria = $crit;

            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $notificationCriteria, $context);
        });

        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $idState = md5(random_int(0, PHP_INT_MAX));

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn($idState);

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $transactionStateHandler->expects($this->never())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'warning' => [
                    'Notification ' . $entity->getId() . ' expired after 1 day',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds
        );

        $this->assertSame(
            [
                'total-count-mode' => 0,
                'associations' => [
                    'orderTransaction' => [
                        'total-count-mode' => 0,
                    ],
                ],
                'sort' => [
                    [
                        'extensions' => [],
                        'field' => 'status',
                        'naturalSorting' => false,
                        'order' => 'ASC',
                    ],
                ],
            ],
            json_decode((string)$notificationCriteria, true)
        );
    }

    #[DataProvider('provideTestDispatchNotification')]
    public function testDispatchNotification($notificationStatus, $hipayStatus, $initialState, $methodExpected, $expectedState)
    {
        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus($notificationStatus);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, PHP_INT_MAX),
            'captured_amount' => random_int(0, PHP_INT_MAX),
            'refunded_amount' => random_int(0, PHP_INT_MAX),
            'status' => $hipayStatus,
            'operation' => [
                'id' => md5(random_int(0, PHP_INT_MAX)),
            ],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        /** @var Criteria|null $notificationCriteria */
        $notificationCriteria = null;
        $notificationRepo->method('search')->willReturnCallback(function ($crit, $context) use (&$notificationCriteria, $notificationCollection) {
            $notificationCriteria = $crit;

            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $notificationCriteria, $context);
        });

        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $idState = md5(random_int(0, PHP_INT_MAX));

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn($idState);

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $transactionStateHandler->expects($this->once())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);


        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Change order transaction ' . $hipayOrder->getTransactionId() . ' to status ' . $expectedState . ' (previously ' . $initialState . ')',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds
        );

        $this->assertSame(
            [
                'total-count-mode' => 0,
                'associations' => [
                    'orderTransaction' => [
                        'total-count-mode' => 0,
                    ],
                ],
                'sort' => [
                    [
                        'extensions' => [],
                        'field' => 'status',
                        'naturalSorting' => false,
                        'order' => 'ASC',
                    ],
                ],
            ],
            json_decode((string)$notificationCriteria, true)
        );
    }

    #[DataProvider('provideTestDispatchNotification')]
    public function testDispatchNotificationWithSameState($notificationStatus, $hipayStatus, $initialState, $methodExpected, $expectedState)
    {
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });
        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus($notificationStatus);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, PHP_INT_MAX),
            'captured_amount' => random_int(0, PHP_INT_MAX),
            'refunded_amount' => random_int(0, PHP_INT_MAX),
            'status' => $hipayStatus,
            'operation' => [
                'id' => md5(random_int(0, PHP_INT_MAX)),
            ],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $transactionStateHandler->expects($this->once())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);


        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Change order transaction ' . $hipayOrder->getTransactionId() . ' to status ' . $expectedState . ' (previously ' . $initialState . ')',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds
        );
    }

    public function testDispatchNotificationAuthorizeAndSaveCard()
    {
        $notificationStatus = NotificationService::AUTHORIZE;
        $hipayStatus = TransactionStatus::AUTHORIZED;
        $initialState = OrderTransactionStates::STATE_IN_PROGRESS;
        $methodExpected = 'authorize';
        $expectedState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus($notificationStatus);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, PHP_INT_MAX),
            'captured_amount' => random_int(0, PHP_INT_MAX),
            'refunded_amount' => random_int(0, PHP_INT_MAX),
            'status' => $hipayStatus,
            'operation' => [
                'id' => md5(random_int(0, PHP_INT_MAX)),
            ],
            'custom_data' => [
                'multiuse' => true,
            ],
            'payment_product' => 'brand_payment_product',
            'payment_method' => [
                'card_id' => 'card_id',
                'token' => 'token',
                'brand' => 'brand_payment_method',
                'pan' => 'pan',
                'card_holder' => 'card_holder',
                'card_expiry_month' => 'card_expiry_month',
                'card_expiry_year' => 'card_expiry_year',
                'issuer' => 'issuer',
                'country' => 'country',
            ],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        /** @var Criteria|null $notificationCriteria */
        $notificationCriteria = null;
        $notificationRepo->method('search')->willReturnCallback(function ($crit, $context) use (&$notificationCriteria, $notificationCollection) {
            $notificationCriteria = $crit;

            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $notificationCriteria, $context);
        });

        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $idState = md5(random_int(0, PHP_INT_MAX));

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn($idState);

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $transactionStateHandler->expects($this->once())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);


        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $upsertToken = [];
        /** @var EntityRepository&MockObject $tokenRepo */
        $tokenRepo = $this->createMock(EntityRepository::class);
        $tokenRepo->method('upsert')->willReturnCallback(function ($arg) use (&$upsertToken) {
            $upsertToken = $arg;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $tokenRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Change order transaction ' . $hipayOrder->getTransactionId() . ' to status ' . $expectedState . ' (previously ' . $initialState . ')',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds
        );

        $entityData = $entity->getData();
        $this->assertSame(
            [[
                'token' => $entityData['payment_method']['token'],
                'brand' => $entityData['payment_product'],
                'pan' => $entityData['payment_method']['pan'],
                'cardHolder' => $entityData['payment_method']['card_holder'],
                'cardExpiryMonth' => $entityData['payment_method']['card_expiry_month'],
                'cardExpiryYear' => $entityData['payment_method']['card_expiry_year'],
                'issuer' => $entityData['payment_method']['issuer'],
                'country' => $entityData['payment_method']['country'],
                'customerId' => 'CUSTOMER_ID',
            ]],
            $upsertToken
        );

        $this->assertSame(
            [
                'total-count-mode' => 0,
                'associations' => [
                    'orderTransaction' => [
                        'total-count-mode' => 0,
                    ],
                ],
                'sort' => [
                    [
                        'extensions' => [],
                        'field' => 'status',
                        'naturalSorting' => false,
                        'order' => 'ASC',
                    ],
                ],
            ],
            json_decode((string)$notificationCriteria, true)
        );
    }

    public static function provideDispatchNotificationWithAuthorize()
    {
        return [
            [NotificationService::PAY_PARTIALLY, TransactionStatus::PARTIALLY_CAPTURED, [TransactionStatus::AUTHORIZED], OrderTransactionStates::STATE_AUTHORIZED, 'payPartially', OrderTransactionStates::STATE_PARTIALLY_PAID],
            [NotificationService::PAID, TransactionStatus::CAPTURED, [TransactionStatus::AUTHORIZED], OrderTransactionStates::STATE_AUTHORIZED, 'paid', OrderTransactionStates::STATE_PAID],
        ];
    }

    #[DataProvider('provideDispatchNotificationWithAuthorize')]
    public function testDispatchNotificationWithAuthorize($notificationStatus, $hipayStatus, $previousHipayStatus, $initialState, $methodExpected, $expectedState)
    {
        $operationId = Uuid::uuid4();

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        // Capture
        $capture = new OrderCaptureEntity();
        $capture->setId('CAPTURE_ID');
        $capture->setOperationId($operationId);
        $capture->setStatus(CaptureStatus::IN_PROGRESS);
        $capture->setHipayOrder($hipayOrder);

        $hipayOrder->setCaptures(new OrderCaptureCollection([$capture]));

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus($notificationStatus);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'refunded_amount' => random_int(0, 1000000),
            'captured_amount' => random_int(0, 1000000),
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        /* @var Criteria|null $criteria */
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $transactionStateHandler->expects($this->once())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $captures = [];
        $captureRepo->method('update')->willReturnCallback(function ($args) use (&$captures) {
            $captures = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Change order transaction ' . $hipayOrder->getTransactionId() . ' to status ' . $expectedState . ' (previously ' . $initialState . ')',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertEquals($capture->getId(), $captures[0]['id']);
        $this->assertEquals($capture->getOperationId(), $captures[0]['operationId']);
        $this->assertEquals(CaptureStatus::COMPLETED, $captures[0]['status']);
    }

    #[DataProvider('provideDispatchNotificationWithAuthorize')]
    public function testDispatchNotificationWithAuthorizeAndWithoutPending($notificationStatus, $hipayStatus, $previousHipayStatus, $initialState, $methodExpected, $expectedState)
    {
        $operationId = Uuid::uuid4();

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        // Capture
        $capture = new OrderCaptureEntity();
        $capture->setId('CAPTURE_ID');
        $capture->setOperationId($operationId);
        $capture->setStatus(CaptureStatus::OPEN);
        $capture->setHipayOrder($hipayOrder);

        $hipayOrder->setCaptures(new OrderCaptureCollection([$capture]));

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus($notificationStatus);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'captured_amount' => random_int(0, 1000000),
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        /* @var Criteria|null $criteria */
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $transactionStateHandler->expects($this->once())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        // Machine State
        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $captures = [];
        $captureRepo->method('update')->willReturnCallback(function ($args) use (&$captures) {
            $captures = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Change order transaction ' . $hipayOrder->getTransactionId() . ' to status ' . $expectedState . ' (previously ' . $initialState . ')',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertEquals($capture->getId(), $captures[0]['id']);
        $this->assertEquals($capture->getOperationId(), $captures[0]['operationId']);
        $this->assertEquals(CaptureStatus::COMPLETED, $captures[0]['status']);
    }

    public function testDispatchNotificationWithAuthorizeAndCaptureNotFound()
    {
        $notificationStatus = NotificationService::PAID;
        $hipayStatus = TransactionStatus::CAPTURED;
        $previousHipayStatus = [TransactionStatus::AUTHORIZED];
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);
        $hipayOrder->setCaptures(new OrderCaptureCollection([]));

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $operationId = Uuid::uuid4();

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus($notificationStatus);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'captured_amount' => random_int(0, 1000000),
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        // Machine State
        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $captures = [];
        $captureRepo->method('create')->willReturnCallback(function ($args) use (&$captures) {
            $captures = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Skipped notification : No capture found with operation ID ' . $operationId . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
            ],
            $logs
        );

        $this->assertSame(
            [],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );
    }

    public function testDispatchNotificationWithAuthorizeAndCreateCapture()
    {
        $notificationStatus = NotificationService::PROCESS_AFTER_AUTHORIZE;
        $hipayStatus = TransactionStatus::CAPTURE_REQUESTED;
        $previousHipayStatus = [TransactionStatus::AUTHORIZED];
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $operationId = Uuid::uuid4();

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus($notificationStatus);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'captured_amount' => random_int(0, 1000000),
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        // Machine State
        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $captures = [];
        $captureRepo->method('create')->willReturnCallback(function ($args) use (&$captures) {
            $captures = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Notification ' . $entity->getId() . ' create IN_PROGRESS capture for the transaction ' . $hipayOrder->getTransactionId(),
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertEquals(null, $captures[0]['id']);
        $this->assertEquals($operationId, $captures[0]['operationId']);
        $this->assertEquals(CaptureStatus::IN_PROGRESS, $captures[0]['status']);
    }

    public function testDispatchNotificationWithAuthorizeAndUpdateCapture()
    {
        $notificationStatus = NotificationService::PROCESS_AFTER_AUTHORIZE;
        $hipayStatus = TransactionStatus::CAPTURE_REQUESTED;
        $previousHipayStatus = [TransactionStatus::AUTHORIZED];
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $operationId = Uuid::uuid4();

        $capture = new OrderCaptureEntity();
        $capture->setOperationId($operationId);
        $capture->setHipayOrderId($hipayOrder->getId());
        $capture->setStatus(CaptureStatus::OPEN);
        $capture->setUniqueIdentifier(md5(random_int(0, PHP_INT_MAX)));
        $capture->setId($capture->getUniqueIdentifier());
        $hipayOrder->setCaptures(new OrderCaptureCollection([$capture]));

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus($notificationStatus);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'captured_amount' => random_int(0, 1000000),
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        // Machine State
        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $captures = [];
        $captureRepo->method('update')->willReturnCallback(function ($args) use (&$captures) {
            $captures = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Notification ' . $entity->getId() . ' update capture ' . $operationId . ' to IN_PROGRESS status for the transaction ' . $hipayOrder->getTransactionId(),
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertEquals($capture->getId(), $captures[0]['id']);
        $this->assertEquals($operationId, $captures[0]['operationId']);
        $this->assertEquals(CaptureStatus::IN_PROGRESS, $captures[0]['status']);
    }

    public function testDispatchNotificationWithAuthorizeAndCaptureAlreadyInProgress()
    {
        $notificationStatus = NotificationService::PROCESS_AFTER_AUTHORIZE;
        $hipayStatus = TransactionStatus::CAPTURE_REQUESTED;
        $previousHipayStatus = [TransactionStatus::AUTHORIZED];
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $operationId = Uuid::uuid4();

        $capture = new OrderCaptureEntity();
        $capture->setOperationId($operationId);
        $capture->setStatus(CaptureStatus::IN_PROGRESS);
        $capture->setUniqueIdentifier(md5(random_int(0, PHP_INT_MAX)));
        $hipayOrder->setCaptures(new OrderCaptureCollection([$capture]));

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus($notificationStatus);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'captured_amount' => random_int(0, 1000000),
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        // Machine State
        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $captures = [];
        $captureRepo->method('create')->willReturnCallback(function ($args) use (&$captures) {
            $captures = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Ignore notification ' . $entity->getId() . '. Capture ' . $operationId . ' already in progress',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );
    }

    public static function provideDispatchNotificationWithCapture()
    {
        return [
            [NotificationService::REFUNDED_PARTIALLY, TransactionStatus::PARTIALLY_REFUNDED, [TransactionStatus::CAPTURED], OrderTransactionStates::STATE_PAID, 'refundPartially', OrderTransactionStates::STATE_PARTIALLY_REFUNDED],
            [NotificationService::REFUNDED, TransactionStatus::REFUNDED, [TransactionStatus::CAPTURED], OrderTransactionStates::STATE_PAID, 'refund', OrderTransactionStates::STATE_REFUNDED],
        ];
    }

    #[DataProvider('provideDispatchNotificationWithCapture')]
    public function testDispatchNotificationWithCapture($notificationStatus, $hipayStatus, $previousHipayStatus, $initialState, $methodExpected, $expectedState)
    {
        $operationId = Uuid::uuid4();

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        // Refund
        $refund = new OrderRefundEntity();
        $refund->setId('REFUND_ID');
        $refund->setOperationId($operationId);
        $refund->setStatus(RefundStatus::OPEN);
        $refund->setHipayOrder($hipayOrder);

        $hipayOrder->setRefunds(new OrderRefundCollection([$refund]));

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus($notificationStatus);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'refunded_amount' => random_int(0, 1000000),
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $transactionStateHandler->expects($this->once())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $refunds = [];
        $refundRepo->method('update')->willReturnCallback(function ($args) use (&$refunds) {
            $refunds = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Change order transaction ' . $hipayOrder->getTransactionId() . ' to status ' . $expectedState . ' (previously ' . $initialState . ')',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertEquals($refund->getId(), $refunds[0]['id']);
        $this->assertEquals($refund->getOperationId(), $refunds[0]['operationId']);
        $this->assertEquals(RefundStatus::COMPLETED, $refunds[0]['status']);
    }

    #[DataProvider('provideDispatchNotificationWithCapture')]
    public function testDispatchNotificationWithCaptureNoCaptureFound($notificationStatus, $hipayStatus, $previousHipayStatus, $initialState, $methodExpected, $expectedState)
    {
        $operationId = Uuid::uuid4();

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        // Capture
        $capture = new OrderCaptureEntity();
        $capture->setId('CAPTURE_ID');
        $capture->setOperationId($operationId);
        $capture->setStatus(CaptureStatus::OPEN);
        $capture->setHipayOrder($hipayOrder);

        $hipayOrder->setCaptures(new OrderCaptureCollection([$capture]));

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus($notificationStatus);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'refunded_amount' => random_int(0, 1000000),
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $transactionStateHandler->expects($this->never())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);
        $captureRepo->expects($this->never())->method('update');

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Skipped notification : No refund found with operation ID ' . $operationId . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
            ],
            $logs
        );

        $this->assertSame(
            [],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );
    }

    public static function provideDispatchFailedNotification()
    {
        return [
            [NotificationService::FAILED, TransactionStatus::AUTHENTICATION_FAILED, [TransactionStatus::AUTHORIZED], OrderTransactionStates::STATE_AUTHORIZED, 'fail', OrderTransactionStates::STATE_FAILED],
        ];
    }

    #[DataProvider('provideDispatchFailedNotification')]
    public function testDispatchFailedNotification($notificationStatus, $hipayStatus, $previousHipayStatus, $initialState, $methodExpected, $expectedState)
    {
        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $operationId = Uuid::uuid4();

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus($notificationStatus);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'refunded_amount' => random_int(0, 1000000),
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $transactionStateHandler->expects($this->once())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Change order transaction ' . $hipayOrder->getTransactionId() . ' to status ' . $expectedState . ' (previously ' . $initialState . ')',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );
    }

    public function testDispatchCaptureRefusedNotification()
    {
        $operationId = Uuid::uuid4();
        $hipayStatus = TransactionStatus::CAPTURE_REFUSED;
        $previousHipayStatus = [TransactionStatus::AUTHORIZED, TransactionStatus::CAPTURE_REQUESTED];
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;
        $expectedState = OrderTransactionStates::STATE_FAILED;

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        // Capture
        $capture = new OrderCaptureEntity();
        $capture->setId('CAPTURE_ID');
        $capture->setOperationId($operationId);
        $capture->setStatus(CaptureStatus::IN_PROGRESS);
        $capture->setHipayOrder($hipayOrder);

        $hipayOrder->setCaptures(new OrderCaptureCollection([$capture]));

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $amount = random_int(0, 1000000);

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus(NotificationService::PROCESS_AFTER_AUTHORIZE);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'captured_amount' => $amount,
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);
        $captureRepo->expects($this->once())->method('update');

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );
    }

    public static function provideDispatchCaptureRefusedNotificationMissingStatus()
    {
        return [
            [[TransactionStatus::CAPTURE_REQUESTED], 'AUTHORIZED'],
            [[TransactionStatus::AUTHORIZED], 'CAPTURE_REQUESTED'],
        ];
    }

    #[DataProvider('provideDispatchCaptureRefusedNotificationMissingStatus')]
    public function testDispatchCaptureRefusedNotificationMissingStatus($previousHipayStatus, $missingStatus)
    {
        $hipayStatus = TransactionStatus::CAPTURE_REFUSED;
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $operationId = Uuid::uuid4();
        $amount = random_int(0, 1000000);

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus(NotificationService::PROCESS_AFTER_AUTHORIZE);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'captured_amount' => $amount,
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $transactionStateHandler->expects($this->never())->method('fail');

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Skipped notification : No ' . $missingStatus . ' notification received for the transaction ' . $hipayOrder->getTransactionId() . ', skip status 173',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );
    }

    public function testDispatchCaptureRefusedNotificationMissingCapture()
    {
        $operationId = Uuid::uuid4();
        $hipayStatus = TransactionStatus::CAPTURE_REFUSED;
        $previousHipayStatus = [TransactionStatus::AUTHORIZED, TransactionStatus::CAPTURE_REQUESTED];
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        // Capture
        $capture = new OrderCaptureEntity();
        $capture->setId('CAPTURE_ID');
        $capture->setOperationId($operationId);
        $capture->setStatus(CaptureStatus::OPEN);
        $capture->setHipayOrder($hipayOrder);

        $hipayOrder->setCaptures(new OrderCaptureCollection([$capture]));

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $amount = random_int(0, 1000000);

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus(NotificationService::PROCESS_AFTER_AUTHORIZE);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'captured_amount' => $amount,
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $transactionStateHandler->expects($this->never())->method('fail');

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        // Machine State
        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idState = md5(random_int(0, PHP_INT_MAX));
        $idSearchResult->method('firstId')->willReturn($idState);

        $hipayOrderRepo->method('searchIds')->willReturn($idSearchResult);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Skipped notification : No IN_PROGRESS capture found with operation ID ' . $operationId . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
            ],
            $logs
        );

        $this->assertSame(
            [],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );
    }

    public function testDispatchRefundRequestedNotification()
    {
        $operationId = Uuid::uuid4();
        $hipayStatus = TransactionStatus::REFUND_REQUESTED;
        $previousHipayStatus = [TransactionStatus::CAPTURED];
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        // Refund
        $refund = new OrderRefundEntity();
        $refund->setId('REFUND_ID');
        $refund->setOperationId($operationId);
        $refund->setStatus(RefundStatus::OPEN);
        $refund->setHipayOrder($hipayOrder);

        $hipayOrder->setRefunds(new OrderRefundCollection([$refund]));

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $amount = random_int(0, 1000000);

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus(NotificationService::PROCESS_AFTER_CAPTURE);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'refunded_amount' => $amount,
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $transactionStateHandler->expects($this->never())->method('fail');

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }
        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        // Machine State
        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);
        $refundRepo->expects($this->once())->method('update');

        $refunds = [];
        $refundRepo->method('update')->willReturnCallback(function ($args) use (&$refunds) {
            $refunds = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Notification ' . $entity->getId() . ' update refund ' . $refunds[0]['id'] . ' to IN_PROGRESS status for the transaction TRX_ID',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertEquals($refund->getId(), $refunds[0]['id']);
        $this->assertEquals($refund->getOperationId(), $refunds[0]['operationId']);
        $this->assertEquals(RefundStatus::IN_PROGRESS, $refunds[0]['status']);
    }

    public function testDispatchRefundRequestedNotificationAndCreateRefund()
    {
        $operationId = Uuid::uuid4();
        $hipayStatus = TransactionStatus::REFUND_REQUESTED;
        $previousHipayStatus = [TransactionStatus::CAPTURED];
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $amount = random_int(0, 1000000);

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus(NotificationService::PROCESS_AFTER_CAPTURE);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'refunded_amount' => $amount,
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $transactionStateHandler->expects($this->never())->method('fail');

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }
        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        // Machine State
        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $refunds = [];
        $refundRepo->method('create')->willReturnCallback(function ($args) use (&$refunds) {
            $refunds = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Notification ' . $entity->getId() . ' create IN_PROGRESS refund for the transaction TRX_ID',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertEquals(null, $refunds[0]['id']);
        $this->assertEquals($operationId, $refunds[0]['operationId']);
        $this->assertEquals(RefundStatus::IN_PROGRESS, $refunds[0]['status']);
    }

    public function testDispatchRefundRequestedNotificationAlreadyInProgress()
    {
        $operationId = Uuid::uuid4();
        $hipayStatus = TransactionStatus::REFUND_REQUESTED;
        $previousHipayStatus = [TransactionStatus::CAPTURED];
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        // Refund
        $refund = new OrderRefundEntity();
        $refund->setId('REFUND_ID');
        $refund->setOperationId($operationId);
        $refund->setStatus(RefundStatus::IN_PROGRESS);
        $refund->setHipayOrder($hipayOrder);

        $hipayOrder->setRefunds(new OrderRefundCollection([$refund]));

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $amount = random_int(0, 1000000);

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus(NotificationService::PROCESS_AFTER_CAPTURE);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'refunded_amount' => $amount,
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $transactionStateHandler->expects($this->never())->method('fail');

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }
        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        // Machine State
        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);
        $refundRepo->expects($this->never())->method('update');

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Ignore notification ' . $entity->getId() . '. Refund ' . $refund->getId() . ' already in progress',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );
    }

    public function testDispatchRefundRefusedNotification()
    {
        $operationId = Uuid::uuid4();
        $hipayStatus = TransactionStatus::REFUND_REFUSED;
        $previousHipayStatus = [TransactionStatus::CAPTURED, TransactionStatus::REFUND_REQUESTED];
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        // Refund
        $refund = new OrderRefundEntity();
        $refund->setId('REFUND_ID');
        $refund->setOperationId($operationId);
        $refund->setStatus(RefundStatus::IN_PROGRESS);
        $refund->setHipayOrder($hipayOrder);

        $hipayOrder->setRefunds(new OrderRefundCollection([$refund]));

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $amount = random_int(0, 1000000);

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus(NotificationService::PROCESS_AFTER_CAPTURE);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'refunded_amount' => $amount,
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $transactionStateHandler->expects($this->never())->method('fail');

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }
        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        // Machine State
        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);
        $refundRepo->expects($this->once())->method('update');

        $refunds = [];
        $refundRepo->method('update')->willReturnCallback(function ($args) use (&$refunds) {
            $refunds = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertEquals($refund->getId(), $refunds[0]['id']);
        $this->assertEquals($refund->getOperationId(), $refunds[0]['operationId']);
        $this->assertEquals(RefundStatus::FAILED, $refunds[0]['status']);
    }

    public function testDispatchRefundRefusedNotificationWithoutRefund()
    {
        $operationId = Uuid::uuid4();
        $hipayStatus = TransactionStatus::REFUND_REFUSED;
        $previousHipayStatus = [TransactionStatus::CAPTURED, TransactionStatus::REFUND_REQUESTED];
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        // Refund
        $refund = new OrderRefundEntity();
        $refund->setId('REFUND_ID');
        $refund->setOperationId($operationId);
        $refund->setStatus(RefundStatus::OPEN);
        $refund->setHipayOrder($hipayOrder);

        $hipayOrder->setRefunds(new OrderRefundCollection([$refund]));

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus(NotificationService::PROCESS_AFTER_CAPTURE);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, 1000000),
            'refunded_amount' => random_int(0, 1000000),
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        // On search notifications
        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($notificationCollection) {
            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $criteria, $context);
        });

        // On delete notifications
        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $transactionStateHandler->expects($this->never())->method('fail');

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }
        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);
        $refundRepo->expects($this->never())->method('update');

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Skipped notification : No IN_PROGRESS refund found with operation ID ' . $operationId . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
            ],
            $logs
        );

        $this->assertSame(
            [],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );
    }

    public static function provideDispatchNotificationWithoutAuthorize()
    {
        return [
            [NotificationService::PROCESS_AFTER_AUTHORIZE, TransactionStatus::CAPTURE_REQUESTED, [], OrderTransactionStates::STATE_OPEN, 'process', 'AUTHORIZED'],
            [NotificationService::PAY_PARTIALLY, TransactionStatus::PARTIALLY_CAPTURED, [], OrderTransactionStates::STATE_OPEN, 'payPartially', 'AUTHORIZED'],
            [NotificationService::PAID, TransactionStatus::CAPTURED, [], OrderTransactionStates::STATE_OPEN, 'paid', 'AUTHORIZED'],
            [NotificationService::REFUNDED_PARTIALLY, TransactionStatus::PARTIALLY_REFUNDED, [], OrderTransactionStates::STATE_OPEN, 'refundPartially', 'CAPTURED | PARTIALLY_CAPTURED'],
            [NotificationService::REFUNDED, TransactionStatus::REFUNDED, [], OrderTransactionStates::STATE_OPEN, 'refund', 'CAPTURED | PARTIALLY_CAPTURED'],
        ];
    }

    #[DataProvider('provideDispatchNotificationWithoutAuthorize')]
    public function testDispatchNotificationWithoutAuthorize($notificationStatus, $hipayStatus, $previousHipayStatus, $initialState, $methodUnexpected, $message)
    {
        // Transaction
        $transaction = $this->generateTransaction($initialState);
        $hipayOrder = $this->generateHipayOrder($transaction, $previousHipayStatus);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $operationId = Uuid::uuid4();

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus($notificationStatus);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'authorized_amount' => random_int(0, PHP_INT_MAX),
            'captured_amount' => random_int(0, PHP_INT_MAX),
            'refunded_amount' => random_int(0, PHP_INT_MAX),
            'status' => $hipayStatus,
            'custom_data' => ['operation_id' => $operationId],
        ]);

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationCollection = new HipayNotificationCollection([$entity]);

        /** @var Criteria|null $criteria */
        $notificationCriteria = null;
        $notificationRepo->method('search')->willReturnCallback(function ($crit, $context) use (&$notificationCriteria, $notificationCollection) {
            $notificationCriteria = $crit;

            return new EntitySearchResult(HipayNotificationEntity::class, $notificationCollection->count(), $notificationCollection, null, $notificationCriteria, $context);
        });

        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $idState = md5(random_int(0, PHP_INT_MAX));

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn($idState);

        // transactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $transactionStateHandler */
        $transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $transactionStateHandler->expects($this->never())->method($methodUnexpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);


        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $refundRepo,
            $this->createMock(EntityRepository::class),
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $transactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $notificationCollection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : ' . count($deletedIds) . ' done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'info' => [
                    'Skipped notification : No ' . $message . ' notification received for the transaction ' . $transaction->getId() . ', skip status ' . $hipayStatus,
                ],
            ],
            $logs
        );

        $this->assertSame(
            [],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );
    }

    public function testDispatchNotificationStatutCodeInvalid()
    {
        // Transaction
        $transaction = $this->generateTransaction();
        $hipayOrder = $this->generateHipayOrder($transaction);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(OrderTransactionEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);

        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setHipayOrder($hipayOrder);
        $entity->setStatus(3000);
        $entity->setCreatedAt(new \DateTime());
        $entity->setData([
            'status' => 0,
        ]);

        $collection = new HipayNotificationCollection([$entity]);

        $notificationRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($collection) {
            return new EntitySearchResult(
                HipayNotificationEntity::class,
                $collection->count(),
                $collection,
                null,
                $criteria,
                $context
            );
        });

        $deletedIds = [];
        $notificationRepo->method('delete')->willReturnCallback(function ($ids) use (&$deletedIds) {
            $deletedIds += $ids;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $config = $this->getReadHipayConfig([
            'hashStage' => 'sha256',
            'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
            'environment' => 'Stage',
        ]);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);


        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $hipayOrderRepo */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($hipayOrder) {
            $collection = new HipayOrderCollection([$hipayOrder]);

            return new EntitySearchResult(HipayOrderEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $service = new NotificationService(
            $transactionRepo,
            $notificationRepo,
            $hipayOrderRepo,
            $captureRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [],
            $deletedIds
        );

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching ' . $collection->count() . ' hipay notifications',
                    'End dispatching Hipay notifications : 0 done',
                ],
                'debug' => [
                    'Dispatching notification ' . $entity->getId() . ' for the transaction ' . $hipayOrder->getTransactionId(),
                ],
                'error' => [
                    'Error during an Hipay notification ' . $entity->getId() . ' dispatching : Bad status code for Hipay notification ' . $entity->getId(),
                ],
            ],
            $logs
        );
    }

    public function testDispatchNotificationOtherProblems()
    {
        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);

        $notificationRepo->method('search')->willThrowException(new \Exception('random exception'));

        $config = $this->getReadHipayConfig([
            'hashStage' => 'sha256',
            'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
            'environment' => 'Stage',
        ]);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);


        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        $service = new NotificationService(
            $this->createMock(EntityRepository::class),
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'error' => [
                    'Error during Hipay notifications dispatching : random exception',
                ],
            ],
            $logs
        );
    }
}
