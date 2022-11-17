<?php

namespace Hipay\Payment\Tests\Unit\Service;

use HiPay\Fullservice\Enum\Transaction\TransactionStatus;
use HiPay\Fullservice\Exception\ApiErrorException;
use HiPay\Payment\Core\Checkout\Payment\HipayNotificationCollection;
use HiPay\Payment\Core\Checkout\Payment\HipayNotificationEntity;
use HiPay\Payment\Service\NotificationService;
use HiPay\Payment\Tests\Tools\ReadHipayConfigServiceMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
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

class NotificationServiceTest extends TestCase
{
    use ReadHipayConfigServiceMockTrait;

    private function addSignature(Request $request, string $algo, string $passphrase)
    {
        $request->headers->add([
            'x-allopass-signature' => hash($algo, $request->getContent().$passphrase),
        ]);

        return $request;
    }

    public function provideSaveNotificationRequest()
    {
        return [
            // FAILED
            [TransactionStatus::AUTHENTICATION_FAILED, NotificationService::FAILED],
            [TransactionStatus::BLOCKED, NotificationService::FAILED],
            [TransactionStatus::DENIED, NotificationService::FAILED],
            [TransactionStatus::REFUSED, NotificationService::FAILED],
            [TransactionStatus::EXPIRED, NotificationService::FAILED],
            [134, NotificationService::FAILED],
            [TransactionStatus::REFUND_REFUSED, NotificationService::FAILED],
            [TransactionStatus::CAPTURE_REFUSED, NotificationService::FAILED],
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
            [TransactionStatus::CAPTURE_REQUESTED, NotificationService::PROCESS_AFTER_AUTHORIZE],
            [TransactionStatus::REFUND_REQUESTED, NotificationService::PROCESS_AFTER_AUTHORIZE],
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

    /**
     * @dataProvider provideSaveNotificationRequest
     */
    public function testSaveNotificationRequest($code, $codeEntity)
    {
        $hipayNotification = [];

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);
        $notificationRepo->method('create')->willReturnCallback(function ($arg) use (&$hipayNotification) {
            $hipayNotification = $arg;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $config = $this->getReadHipayConfig([
            'hashStage' => 'sha256',
            'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
            'environment' => 'Stage',
        ]);

        $service = new NotificationService(
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            new NullLogger()
        );

        $content = [
            'status' => $code,
            'date_updated' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:sO'),
            'transaction_reference' => random_int(0, PHP_INT_MAX),
            'order' => [
                'id' => random_int(0, PHP_INT_MAX),
            ],
        ];
        $request = new Request([], $content);

        $service->saveNotificationRequest(
            $this->addSignature($request, $config->getHash(), $config->getPassphrase())
        );

        $this->assertSame(
            [[
                'status' => $codeEntity,
                'data' => $request->request->all(),
                'notificationUpdatedAt' => (new \DateTimeImmutable($content['date_updated']))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'orderTransaction' => [
                    'id' => $content['order']['id'],
                    'customFields' => [
                        'hipay_transaction_reference' => $content['transaction_reference'],
                    ],
                ],
            ]],
            $hipayNotification
        );
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
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            new NullLogger()
        );

        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage('Bad configuration unknown algorythm "TEST"');

        $service->saveNotificationRequest(new Request());
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
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            new NullLogger()
        );

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('Missing signature header');

        $service->saveNotificationRequest(new Request());
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
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            new NullLogger()
        );

        $request = new Request();
        $request->headers->add([
            'x-allopass-signature' => 'badSignature',
        ]);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Signature does not match');

        $service->saveNotificationRequest($request);
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
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            new NullLogger()
        );

        $request = new Request([], ['foo' => 'bar']);

        $this->expectException(MissingMandatoryParametersException::class);
        $this->expectExceptionMessage('date_updated is mandatory');

        $service->saveNotificationRequest(
            $this->addSignature($request, $config->getHash(), $config->getPassphrase())
        );
    }

    public function testSaveNotificationRequestOrderIdMissing()
    {
        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);

        $config = $this->getReadHipayConfig([
            'hashStage' => 'sha256',
            'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
            'environment' => 'Stage',
        ]);

        $service = new NotificationService(
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            new NullLogger()
        );

        $content = [
            'date_updated' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:sO'),
        ];
        $request = new Request([], $content);

        $this->expectException(MissingMandatoryParametersException::class);
        $this->expectExceptionMessage('order.id is mandatory');

        $service->saveNotificationRequest(
            $this->addSignature($request, $config->getHash(), $config->getPassphrase())
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
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            new NullLogger()
        );

        $content = [
            'date_updated' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:sO'),
            'order' => ['id' => random_int(0, PHP_INT_MAX)],
        ];
        $request = new Request([], $content);

        $this->expectExceptionMessage('transaction_reference is mandatory');
        $this->expectException(MissingMandatoryParametersException::class);

        $service->saveNotificationRequest(
            $this->addSignature($request, $config->getHash(), $config->getPassphrase())
        );
    }

    public function testSaveNotificationRequestInvalidStatus()
    {
        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);

        $config = $this->getReadHipayConfig([
            'hashStage' => 'sha256',
            'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
            'environment' => 'Stage',
        ]);

        $service = new NotificationService(
            $notificationRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $config,
            $this->createMock(OrderTransactionStateHandler::class),
            new NullLogger()
        );

        $content = [
            'status' => 0,
            'date_updated' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:sO'),
            'order' => ['id' => random_int(0, PHP_INT_MAX)],
            'transaction_reference' => random_int(0, PHP_INT_MAX),
        ];
        $request = new Request([], $content);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Status code "0" invalid');

        $service->saveNotificationRequest(
            $this->addSignature($request, $config->getHash(), $config->getPassphrase())
        );
    }

    private function generateTransaction($initialState, $initialCustomFields = [])
    {
        $stateMachine = new StateMachineStateEntity();
        $stateMachine->setTechnicalName($initialState);

        $transaction = new OrderTransactionEntity();
        $transaction->setId(md5(random_int(0, PHP_INT_MAX)));
        $transaction->setStateMachineState($stateMachine);
        $transaction->setCustomFields($initialCustomFields);

        return $transaction;
    }

    public function provideTestDispatchNotification()
    {
        return [
            [NotificationService::PROCESS, TransactionStatus::AUTHORIZED_AND_PENDING, OrderTransactionStates::STATE_OPEN, 'process', OrderTransactionStates::STATE_IN_PROGRESS],
            [NotificationService::FAILED, TransactionStatus::AUTHENTICATION_FAILED, OrderTransactionStates::STATE_IN_PROGRESS, 'fail', OrderTransactionStates::STATE_FAILED],
            [NotificationService::CHARGEDBACK, TransactionStatus::CHARGED_BACK, OrderTransactionStates::STATE_IN_PROGRESS, 'chargeback', OrderTransactionStates::STATE_CHARGEBACK],
            [NotificationService::AUTHORIZE, TransactionStatus::AUTHORIZED, OrderTransactionStates::STATE_IN_PROGRESS, 'authorize', OrderTransactionStates::STATE_AUTHORIZED],
            [NotificationService::CANCELLED, TransactionStatus::CANCELLED, OrderTransactionStates::STATE_IN_PROGRESS, 'cancel', OrderTransactionStates::STATE_CANCELLED],
        ];
    }

    /**
     * @dataProvider provideTestDispatchNotification
     */
    public function testDispatchNotification($notificationStatus, $hipayStatus, $initialState, $methodExpected, $expectedState)
    {
        // Transaction
        $initialCustomFields = rand(0, 1) ? [] : ['hipay_status' => [100]];
        $transaction = $this->generateTransaction($initialState, $initialCustomFields);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $customFields = [];
        $transactionRepo->method('update')->willReturnCallback(function ($args) use (&$customFields) {
            $customFields = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus($notificationStatus);
        $entity->setData([
            'captured_amount' => random_int(0, PHP_INT_MAX),
            'refunded_amount' => random_int(0, PHP_INT_MAX),
            'status' => $hipayStatus,
            'operation_id' => md5(random_int(0, PHP_INT_MAX)),
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

        // TransactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $TransactionStateHandler */
        $TransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $TransactionStateHandler->expects($this->once())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $machineStateRepo */
        $machineStateRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $notificationRepo,
            $transactionRepo,
            $captureRepo,
            $refundRepo,
            $machineStateRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $TransactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching '.$notificationCollection->count().' hipay notifications',
                    'End dispatching Hipay notifications : '.count($deletedIds).' done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'info' => [
                    'Change order transaction '.$entity->getOrderTransactionId().' to status '.$expectedState.' (previously '.$initialState.')',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds
        );

        $this->assertSame(
            [[
                'id' => $transaction->getId(),
                'customFields' => array_merge_recursive($initialCustomFields, ['hipay_status' => [$hipayStatus]]),
            ]],
            $customFields
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
                        'field' => 'status',
                        'naturalSorting' => false,
                        'extensions' => [],
                        'order' => 'ASC',
                    ],
                ],
              ],
            json_decode((string) $notificationCriteria, true)
        );
    }

    /**
     * @dataProvider provideTestDispatchNotification
     */
    public function testDispatchNotificationWithSameState($notificationStatus, $hipayStatus, $initialState, $methodExpected, $expectedState)
    {
        $transaction = $this->generateTransaction($expectedState);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });
        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus($notificationStatus);
        $entity->setData([
            'captured_amount' => random_int(0, PHP_INT_MAX),
            'refunded_amount' => random_int(0, PHP_INT_MAX),
            'status' => $hipayStatus,
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

        // TransactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $TransactionStateHandler */
        $TransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $TransactionStateHandler->expects($this->never())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $machineStateRepo */
        $machineStateRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $notificationRepo,
            $transactionRepo,
            $captureRepo,
            $refundRepo,
            $machineStateRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $TransactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching '.$notificationCollection->count().' hipay notifications',
                    'End dispatching Hipay notifications : '.count($deletedIds).' done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'info' => [
                    'Ignore notification '.$entity->getId().'. Transaction '.$entity->getOrderTransactionId().' already have status '.$expectedState,
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds
        );
    }

    public function provideDispatchNotificationWithAuthorize()
    {
        return [
            [NotificationService::PAY_PARTIALLY, TransactionStatus::PARTIALLY_CAPTURED, [TransactionStatus::AUTHORIZED], OrderTransactionStates::STATE_AUTHORIZED, 'payPartially', OrderTransactionStates::STATE_PARTIALLY_PAID],
            [NotificationService::PAID, TransactionStatus::CAPTURED, [TransactionStatus::AUTHORIZED], OrderTransactionStates::STATE_AUTHORIZED, 'paid', OrderTransactionStates::STATE_PAID],
        ];
    }

    /**
     * @dataProvider provideDispatchNotificationWithAuthorize
     */
    public function testDispatchNotificationWithAuthorize($notificationStatus, $hipayStatus, $previousHipayStatus, $initialState, $methodExpected, $expectedState)
    {
        // Transaction
        $initialCustomFields = ['hipay_status' => $previousHipayStatus];
        $transaction = $this->generateTransaction($initialState, $initialCustomFields);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $customFields = [];
        $transactionRepo->method('update')->willReturnCallback(function ($args) use (&$customFields) {
            $customFields = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $operationId = Uuid::uuid4();

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus($notificationStatus);
        $entity->setData([
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

        $idState = md5(random_int(0, PHP_INT_MAX));

        // TransactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $TransactionStateHandler */
        $TransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $TransactionStateHandler->expects($this->once())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        // Capture
        /** @var IdSearchResult&MockObject $searchIdResultCapture */
        $searchIdResultCapture = $this->createMock(IdSearchResult::class);
        $idCapture = md5(random_int(0, PHP_INT_MAX));
        $searchIdResultCapture->method('firstId')->willReturn($idCapture);

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);
        $captureRepo->method('searchIds')->willReturn($searchIdResultCapture);

        // Machine State
        /** @var EntityRepository&MockObject $machineStateRepo */
        $machineStateRepo = $this->createMock(EntityRepository::class);

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idState = md5(random_int(0, PHP_INT_MAX));
        $idSearchResult->method('firstId')->willReturn($idState);

        $machineStateRepo->expects($this->exactly(2))->method('searchIds')->willReturn($idSearchResult);

        $captures = [];
        $captureRepo->method('update')->willReturnCallback(function ($args) use (&$captures) {
            $captures = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $amount = $entity->getData()['captured_amount'];
        $expectedCaptures = [[
            'id' => $idCapture,
            'orderTransactionId' => $transaction->getId(),
            'stateId' => $idState,
            'totalAmount' => $amount,
            'amount' => new CalculatedPrice(
                $amount,
                $amount,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            ),
            'externalReference' => $operationId->toString(),
        ]];

        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $notificationRepo,
            $transactionRepo,
            $captureRepo,
            $refundRepo,
            $machineStateRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $TransactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching '.$notificationCollection->count().' hipay notifications',
                    'End dispatching Hipay notifications : '.count($deletedIds).' done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'info' => [
                    'Change order transaction '.$entity->getOrderTransactionId().' to status '.$expectedState.' (previously '.$initialState.')',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertSame(
            [[
                'id' => $transaction->getId(),
                'customFields' => array_merge_recursive($initialCustomFields, ['hipay_status' => [$hipayStatus]]),
            ]],
            $customFields
        );

        $this->assertEquals(
            $expectedCaptures,
            $captures,
            'Captures creation missmatch'
        );
    }

    /**
     * @dataProvider provideDispatchNotificationWithAuthorize
     */
    public function testDispatchNotificationWithAuthorizeAndWithoutPending($notificationStatus, $hipayStatus, $previousHipayStatus, $initialState, $methodExpected, $expectedState)
    {
        // Transaction
        $initialCustomFields = ['hipay_status' => $previousHipayStatus];
        $transaction = $this->generateTransaction($initialState, $initialCustomFields);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $customFields = [];
        $transactionRepo->method('update')->willReturnCallback(function ($args) use (&$customFields) {
            $customFields = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $operationId = Uuid::uuid4();

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus($notificationStatus);
        $entity->setData([
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

        $idState = md5(random_int(0, PHP_INT_MAX));

        // TransactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $TransactionStateHandler */
        $TransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $TransactionStateHandler->expects($this->never())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        // Capture
        /** @var IdSearchResult&MockObject $searchIdResultCapture */
        $searchIdResultCapture = $this->createMock(IdSearchResult::class);
        $idCapture = null;
        $searchIdResultCapture->method('firstId')->willReturn($idCapture);

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);
        $captureRepo->method('searchIds')->willReturn($searchIdResultCapture);

        // Machine State
        /** @var EntityRepository&MockObject $machineStateRepo */
        $machineStateRepo = $this->createMock(EntityRepository::class);

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idState = md5(random_int(0, PHP_INT_MAX));
        $idSearchResult->method('firstId')->willReturn($idState);

        $machineStateRepo->expects($this->exactly(1))->method('searchIds')->willReturn($idSearchResult);

        $captures = [];
        $captureRepo->method('update')->willReturnCallback(function ($args) use (&$captures) {
            $captures = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $notificationRepo,
            $transactionRepo,
            $captureRepo,
            $refundRepo,
            $machineStateRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $TransactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching '.$notificationCollection->count().' hipay notifications',
                    'End dispatching Hipay notifications : '.count($deletedIds).' done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'info' => [
                    'Skipped notification : No PENDING capture found for the transaction '.$entity->getOrderTransactionId(),
                ],
            ],
            $logs
        );

        $this->assertSame(
            [],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertSame(
            [],
            $customFields
        );

        $this->assertEquals(
            [],
            $captures,
            'Captures creation missmatch'
        );
    }

    public function testDispatchNotificationWithAuthorizeAndCreateCapture()
    {
        $notificationStatus = NotificationService::PROCESS_AFTER_AUTHORIZE;
        $hipayStatus = TransactionStatus::CAPTURE_REQUESTED;
        $previousHipayStatus = [TransactionStatus::AUTHORIZED];
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $initialCustomFields = ['hipay_status' => $previousHipayStatus];
        $transaction = $this->generateTransaction($initialState, $initialCustomFields);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $customFields = [];
        $transactionRepo->method('update')->willReturnCallback(function ($args) use (&$customFields) {
            $customFields = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $operationId = Uuid::uuid4();

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus($notificationStatus);
        $entity->setData([
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

        $idState = md5(random_int(0, PHP_INT_MAX));

        // TransactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $TransactionStateHandler */
        $TransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        // Capture
        /** @var IdSearchResult&MockObject $searchIdResultCapture */
        $searchIdResultCapture = $this->createMock(IdSearchResult::class);
        $idCapture = null;
        $searchIdResultCapture->method('firstId')->willReturn($idCapture);

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);
        $captureRepo->method('searchIds')->willReturn($searchIdResultCapture);

        // Machine State
        /** @var EntityRepository&MockObject $machineStateRepo */
        $machineStateRepo = $this->createMock(EntityRepository::class);

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idState = md5(random_int(0, PHP_INT_MAX));
        $idSearchResult->method('firstId')->willReturn($idState);

        $machineStateRepo->expects($this->exactly(2))->method('searchIds')->willReturn($idSearchResult);

        $captures = [];
        $captureRepo->method('create')->willReturnCallback(function ($args) use (&$captures) {
            $captures = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $amount = $entity->getData()['captured_amount'];
        $expectedCaptures = [[
            'id' => null,
            'orderTransactionId' => $transaction->getId(),
            'stateId' => $idState,
            'totalAmount' => $amount,
            'amount' => new CalculatedPrice(
                $amount,
                $amount,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            ),
            'externalReference' => $operationId->toString(),
        ]];

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $notificationRepo,
            $transactionRepo,
            $captureRepo,
            $refundRepo,
            $machineStateRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $TransactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching '.$notificationCollection->count().' hipay notifications',
                    'End dispatching Hipay notifications : '.count($deletedIds).' done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'info' => [
                    'Notification '.$entity->getId().' create PENDING capture for the transaction '.$transaction->getId(),
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertSame(
            [[
                'id' => $transaction->getId(),
                'customFields' => array_merge_recursive($initialCustomFields, ['hipay_status' => [$hipayStatus]]),
            ]],
            $customFields
        );

        $this->assertEquals(
            $expectedCaptures,
            $captures,
            'Captures creation missmatch'
        );
    }

    public function provideDispatchNotificationWithCapture()
    {
        return [
            [NotificationService::REFUNDED_PARTIALLY, TransactionStatus::PARTIALLY_REFUNDED, [TransactionStatus::CAPTURED], OrderTransactionStates::STATE_PAID, 'refundPartially', OrderTransactionStates::STATE_PARTIALLY_REFUNDED],
            [NotificationService::REFUNDED, TransactionStatus::REFUNDED, [TransactionStatus::CAPTURED], OrderTransactionStates::STATE_PAID, 'refund', OrderTransactionStates::STATE_REFUNDED],
           ];
    }

    /**
     * @dataProvider provideDispatchNotificationWithCapture
     */
    public function testDispatchNotificationWithCapture($notificationStatus, $hipayStatus, $previousHipayStatus, $initialState, $methodExpected, $expectedState)
    {
        // Transaction
        $initialCustomFields = ['hipay_status' => $previousHipayStatus];
        $transaction = $this->generateTransaction($initialState, $initialCustomFields);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $customFields = [];
        $transactionRepo->method('update')->willReturnCallback(function ($args) use (&$customFields) {
            $customFields = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $operationId = Uuid::uuid4();

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus($notificationStatus);
        $entity->setData([
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

        $idState = md5(random_int(0, PHP_INT_MAX));

        // TransactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $TransactionStateHandler */
        $TransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $TransactionStateHandler->expects($this->once())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        // Capture
        /** @var IdSearchResult&MockObject $searchIdResultCapture */
        $searchIdResultCapture = $this->createMock(IdSearchResult::class);
        $idCapture = md5(random_int(0, PHP_INT_MAX));
        $searchIdResultCapture->method('firstId')->willReturn($idCapture);

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);
        $captureRepo->method('searchIds')->willReturn($searchIdResultCapture);

        // Machine State
        /** @var EntityRepository&MockObject $machineStateRepo */
        $machineStateRepo = $this->createMock(EntityRepository::class);

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idState = md5(random_int(0, PHP_INT_MAX));
        $idSearchResult->method('firstId')->willReturn($idState);

        $machineStateRepo->method('searchIds')->willReturn($idSearchResult);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        /** @var IdSearchResult&MockObject $idSearchResultRefund */
        $idSearchResultRefund = $this->createMock(IdSearchResult::class);
        $idRefund = md5(random_int(0, PHP_INT_MAX));
        $idSearchResultRefund->method('firstId')->willReturn($idRefund);

        $refundRepo->method('searchIds')->willReturn($idSearchResultRefund);

        $refunds = [];
        $refundRepo->method('update')->willReturnCallback(function ($args) use (&$refunds) {
            $refunds = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $expectedRefunds = [
            [
                'id' => $idRefund,
                'stateId' => $idState,
            ],
            ];

        $service = new NotificationService(
            $notificationRepo,
            $transactionRepo,
            $captureRepo,
            $refundRepo,
            $machineStateRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $TransactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching '.$notificationCollection->count().' hipay notifications',
                    'End dispatching Hipay notifications : '.count($deletedIds).' done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'info' => [
                    'Change order transaction '.$entity->getOrderTransactionId().' to status '.$expectedState.' (previously '.$initialState.')',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertSame(
            [[
                'id' => $transaction->getId(),
                'customFields' => array_merge_recursive($initialCustomFields, ['hipay_status' => [$hipayStatus]]),
            ]],
            $customFields
        );

        $this->assertEquals(
            $expectedRefunds,
            $refunds,
            'Refunds creation missmatch'
        );
    }

    /**
     * @dataProvider provideDispatchNotificationWithCapture
     */
    public function testDispatchNotificationWithCaptureNoCaptureFound($notificationStatus, $hipayStatus, $previousHipayStatus, $initialState, $methodExpected, $expectedState)
    {
        // Transaction
        $initialCustomFields = ['hipay_status' => $previousHipayStatus];
        $transaction = $this->generateTransaction($initialState, $initialCustomFields);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $customFields = [];
        $transactionRepo->method('update')->willReturnCallback(function ($args) use (&$customFields) {
            $customFields = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $operationId = Uuid::uuid4();

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus($notificationStatus);
        $entity->setData([
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

        $idState = md5(random_int(0, PHP_INT_MAX));

        // TransactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $TransactionStateHandler */
        $TransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $TransactionStateHandler->expects($this->never())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        // Capture
        /** @var IdSearchResult&MockObject $searchIdResultCapture */
        $searchIdResultCapture = $this->createMock(IdSearchResult::class);
        $idCapture = md5(random_int(0, PHP_INT_MAX));
        $searchIdResultCapture->method('firstId')->willReturn($idCapture);

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);
        $captureRepo->method('searchIds')->willReturn($searchIdResultCapture);

        // Machine State
        /** @var EntityRepository&MockObject $machineStateRepo */
        $machineStateRepo = $this->createMock(EntityRepository::class);

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idState = md5(random_int(0, PHP_INT_MAX));
        $idSearchResult->method('firstId')->willReturn($idState);

        $machineStateRepo->method('searchIds')->willReturn($idSearchResult);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        /** @var IdSearchResult&MockObject $idSearchResultRefund */
        $idSearchResultRefund = $this->createMock(IdSearchResult::class);
        $idRefund = null;
        $idSearchResultRefund->method('firstId')->willReturn($idRefund);

        $refundRepo->method('searchIds')->willReturn($idSearchResultRefund);

        $service = new NotificationService(
            $notificationRepo,
            $transactionRepo,
            $captureRepo,
            $refundRepo,
            $machineStateRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $TransactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching '.$notificationCollection->count().' hipay notifications',
                    'End dispatching Hipay notifications : '.count($deletedIds).' done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'info' => [
                    'Skipped notification : No PENDING open or in_progress refund found for the transaction '.$entity->getOrderTransactionId(),
                ],
            ],
            $logs
        );

        $this->assertSame(
            [],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertSame(
            [],
            $customFields
        );
    }

    public function provideDispatchFailedNotification()
    {
        return [
            [NotificationService::FAILED, TransactionStatus::AUTHENTICATION_FAILED, [TransactionStatus::AUTHORIZED], OrderTransactionStates::STATE_AUTHORIZED, 'fail', OrderTransactionStates::STATE_FAILED],
        ];
    }

    /**
     * @dataProvider provideDispatchFailedNotification
     */
    public function testDispatchFailedNotification($notificationStatus, $hipayStatus, $previousHipayStatus, $initialState, $methodExpected, $expectedState)
    {
        // Transaction
        $initialCustomFields = ['hipay_status' => $previousHipayStatus];
        $transaction = $this->generateTransaction($initialState, $initialCustomFields);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $customFields = [];
        $transactionRepo->method('update')->willReturnCallback(function ($args) use (&$customFields) {
            $customFields = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $operationId = Uuid::uuid4();

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus($notificationStatus);
        $entity->setData([
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

        // TransactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $TransactionStateHandler */
        $TransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $TransactionStateHandler->expects($this->once())->method($methodExpected);

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        /** @var EntityRepository&MockObject $machineStateRepo */
        $machineStateRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $notificationRepo,
            $transactionRepo,
            $captureRepo,
            $refundRepo,
            $machineStateRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $TransactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching '.$notificationCollection->count().' hipay notifications',
                    'End dispatching Hipay notifications : '.count($deletedIds).' done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'info' => [
                    'Change order transaction '.$entity->getOrderTransactionId().' to status '.$expectedState.' (previously '.$initialState.')',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertSame(
            [[
                'id' => $transaction->getId(),
                'customFields' => array_merge_recursive($initialCustomFields, ['hipay_status' => [$hipayStatus]]),
            ]],
            $customFields
        );
    }

    public function testDispatchCaptureRefusedNotification()
    {
        $hipayStatus = TransactionStatus::CAPTURE_REFUSED;
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;
        $expectedState = OrderTransactionStates::STATE_FAILED;
        // Transaction
        $initialCustomFields = ['hipay_status' => [TransactionStatus::AUTHORIZED, TransactionStatus::CAPTURE_REQUESTED]];
        $transaction = $this->generateTransaction($initialState, $initialCustomFields);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $customFields = [];
        $transactionRepo->method('update')->willReturnCallback(function ($args) use (&$customFields) {
            $customFields = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $operationId = Uuid::uuid4();
        $amount = random_int(0, 1000000);

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus(NotificationService::FAILED);
        $entity->setData([
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

        $idState = md5(random_int(0, PHP_INT_MAX));

        // TransactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $TransactionStateHandler */
        $TransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $TransactionStateHandler->expects($this->once())->method('fail');

        // Logger
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logs = [];
        foreach (get_class_methods(LoggerInterface::class) as $method) {
            $logger->method($method)->willReturnCallback(function ($message) use (&$logs, $method) {
                $logs[$method][] = $message;
            });
        }

        // Capture
        /** @var IdSearchResult&MockObject $searchIdResultCapture */
        $searchIdResultCapture = $this->createMock(IdSearchResult::class);
        $idCapture = md5(random_int(0, PHP_INT_MAX));
        $searchIdResultCapture->method('firstId')->willReturn($idCapture);

        /** @var EntityRepository&MockObject $captureRepo */
        $captureRepo = $this->createMock(EntityRepository::class);
        $captureRepo->method('searchIds')->willReturn($searchIdResultCapture);

        // Machine State
        /** @var EntityRepository&MockObject $machineStateRepo */
        $machineStateRepo = $this->createMock(EntityRepository::class);

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idState = md5(random_int(0, PHP_INT_MAX));
        $idSearchResult->method('firstId')->willReturn($idState);

        $machineStateRepo->method('searchIds')->willReturn($idSearchResult);

        $captures = [];
        $captureRepo->method('update')->willReturnCallback(function ($args) use (&$captures) {
            $captures = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $expectedCaptures = [[
            'id' => $idCapture,
            'orderTransactionId' => $transaction->getId(),
            'stateId' => $idState,
            'totalAmount' => $amount,
            'amount' => new CalculatedPrice(
                $amount,
                $amount,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            ),
            'externalReference' => $operationId->toString(),
        ]];

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $notificationRepo,
            $transactionRepo,
            $captureRepo,
            $refundRepo,
            $machineStateRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $TransactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching '.$notificationCollection->count().' hipay notifications',
                    'End dispatching Hipay notifications : '.count($deletedIds).' done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'info' => [
                    'Change order transaction '.$entity->getOrderTransactionId().' to status '.$expectedState.' (previously '.$initialState.')',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertSame(
            [[
                'id' => $transaction->getId(),
                'customFields' => array_merge_recursive($initialCustomFields, ['hipay_status' => [$hipayStatus]]),
            ]],
            $customFields
        );

        $this->assertEquals(
            $expectedCaptures,
            $captures,
            'Captures creation missmatch'
        );
    }

    public function provideDispatchCaptureRefusedNotificationMissingStatus()
    {
        return [
            [[TransactionStatus::CAPTURE_REQUESTED], 'AUTHORIZED'],
            [[TransactionStatus::AUTHORIZED], 'CAPTURE_REQUESTED'],
        ];
    }

    /**
     * @dataProvider provideDispatchCaptureRefusedNotificationMissingStatus
     */
    public function testDispatchCaptureRefusedNotificationMissingStatus($previousHipayStatus, $missingStatus)
    {
        $hipayStatus = TransactionStatus::CAPTURE_REFUSED;
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $initialCustomFields = ['hipay_status' => $previousHipayStatus];
        $transaction = $this->generateTransaction($initialState, $initialCustomFields);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $operationId = Uuid::uuid4();
        $amount = random_int(0, 1000000);

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus(NotificationService::FAILED);
        $entity->setData([
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

        // TransactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $TransactionStateHandler */
        $TransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $TransactionStateHandler->expects($this->never())->method('fail');

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
        /** @var EntityRepository&MockObject $machineStateRepo */
        $machineStateRepo = $this->createMock(EntityRepository::class);

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idState = md5(random_int(0, PHP_INT_MAX));
        $idSearchResult->method('firstId')->willReturn($idState);

        $machineStateRepo->method('searchIds')->willReturn($idSearchResult);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $notificationRepo,
            $transactionRepo,
            $captureRepo,
            $refundRepo,
            $machineStateRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $TransactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching '.$notificationCollection->count().' hipay notifications',
                    'End dispatching Hipay notifications : '.count($deletedIds).' done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'info' => [
                    'Skipped notification : No '.$missingStatus.' notification receive for the transaction '.$entity->getOrderTransactionId().', skip status 173',
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
        $hipayStatus = TransactionStatus::CAPTURE_REFUSED;
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;

        // Transaction
        $initialCustomFields = ['hipay_status' => [TransactionStatus::AUTHORIZED, TransactionStatus::CAPTURE_REQUESTED]];
        $transaction = $this->generateTransaction($initialState, $initialCustomFields);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $operationId = Uuid::uuid4();
        $amount = random_int(0, 1000000);

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus(NotificationService::FAILED);
        $entity->setData([
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

        // TransactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $TransactionStateHandler */
        $TransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $TransactionStateHandler->expects($this->never())->method('fail');

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
        /** @var EntityRepository&MockObject $machineStateRepo */
        $machineStateRepo = $this->createMock(EntityRepository::class);

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idState = md5(random_int(0, PHP_INT_MAX));
        $idSearchResult->method('firstId')->willReturn($idState);

        $machineStateRepo->method('searchIds')->willReturn($idSearchResult);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        $service = new NotificationService(
            $notificationRepo,
            $transactionRepo,
            $captureRepo,
            $refundRepo,
            $machineStateRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $TransactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching '.$notificationCollection->count().' hipay notifications',
                    'End dispatching Hipay notifications : '.count($deletedIds).' done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'info' => [
                    'Skipped notification : No PENDING capture found for the transaction '.$entity->getOrderTransactionId(),
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

    public function testDispatchRefundRefusedNotification()
    {
        $hipayStatus = TransactionStatus::REFUND_REFUSED;
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;
        $expectedState = OrderTransactionStates::STATE_FAILED;
        // Transaction
        $initialCustomFields = ['hipay_status' => [TransactionStatus::AUTHORIZED, TransactionStatus::CAPTURED]];
        $transaction = $this->generateTransaction($initialState, $initialCustomFields);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $customFields = [];
        $transactionRepo->method('update')->willReturnCallback(function ($args) use (&$customFields) {
            $customFields = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $operationId = Uuid::uuid4();
        $amount = random_int(0, 1000000);

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus(NotificationService::FAILED);
        $entity->setData([
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

        $idState = md5(random_int(0, PHP_INT_MAX));

        // TransactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $TransactionStateHandler */
        $TransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $TransactionStateHandler->expects($this->once())->method('fail');

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
        /** @var EntityRepository&MockObject $machineStateRepo */
        $machineStateRepo = $this->createMock(EntityRepository::class);

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idState = md5(random_int(0, PHP_INT_MAX));
        $idSearchResult->method('firstId')->willReturn($idState);

        $machineStateRepo->method('searchIds')->willReturn($idSearchResult);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        /** @var IdSearchResult&MockObject $searchIdResultRefund */
        $searchIdResultRefund = $this->createMock(IdSearchResult::class);
        $idRefund = md5(random_int(0, PHP_INT_MAX));
        $searchIdResultRefund->method('firstId')->willReturn($idRefund);

        $refundRepo->method('searchIds')->willReturn($searchIdResultRefund);

        $refunds = [];
        $refundRepo->method('update')->willReturnCallback(function ($args) use (&$refunds) {
            $refunds = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $expectedRefunds = [[
             'id' => $idRefund,
             'stateId' => $idState,
        ]];

        $service = new NotificationService(
            $notificationRepo,
            $transactionRepo,
            $captureRepo,
            $refundRepo,
            $machineStateRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $TransactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching '.$notificationCollection->count().' hipay notifications',
                    'End dispatching Hipay notifications : '.count($deletedIds).' done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'info' => [
                    'Change order transaction '.$entity->getOrderTransactionId().' to status '.$expectedState.' (previously '.$initialState.')',
                ],
            ],
            $logs
        );

        $this->assertSame(
            [['id' => $entity->getId()]],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertSame(
            [[
                'id' => $transaction->getId(),
                'customFields' => array_merge_recursive($initialCustomFields, ['hipay_status' => [$hipayStatus]]),
            ]],
            $customFields
        );

        $this->assertEquals(
            $expectedRefunds,
            $refunds,
            'Captures creation missmatch'
        );
    }

    public function testDispatchRefundRefusedNotificationWithoutRefund()
    {
        $hipayStatus = TransactionStatus::REFUND_REFUSED;
        $initialState = OrderTransactionStates::STATE_AUTHORIZED;
        $expectedState = OrderTransactionStates::STATE_FAILED;
        // Transaction
        $initialCustomFields = ['hipay_status' => [TransactionStatus::AUTHORIZED, TransactionStatus::CAPTURED]];
        $transaction = $this->generateTransaction($initialState, $initialCustomFields);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $customFields = [];
        $transactionRepo->method('update')->willReturnCallback(function ($args) use (&$customFields) {
            $customFields = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $operationId = Uuid::uuid4();
        $amount = random_int(0, 1000000);

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus(NotificationService::FAILED);
        $entity->setData([
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

        // TransactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $TransactionStateHandler */
        $TransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        // check that the expected method was call
        $TransactionStateHandler->expects($this->never())->method('fail');

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
        /** @var EntityRepository&MockObject $machineStateRepo */
        $machineStateRepo = $this->createMock(EntityRepository::class);

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idState = md5(random_int(0, PHP_INT_MAX));
        $idSearchResult->method('firstId')->willReturn($idState);

        $machineStateRepo->method('searchIds')->willReturn($idSearchResult);

        /** @var EntityRepository&MockObject $refundRepo */
        $refundRepo = $this->createMock(EntityRepository::class);

        /** @var IdSearchResult&MockObject $searchIdResultRefund */
        $searchIdResultRefund = $this->createMock(IdSearchResult::class);
        $idRefund = null;
        $searchIdResultRefund->method('firstId')->willReturn($idRefund);

        $refundRepo->method('searchIds')->willReturn($searchIdResultRefund);

        $refunds = [];
        $refundRepo->method('update')->willReturnCallback(function ($args) use (&$refunds) {
            $refunds = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $service = new NotificationService(
            $notificationRepo,
            $transactionRepo,
            $captureRepo,
            $refundRepo,
            $machineStateRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $TransactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching '.$notificationCollection->count().' hipay notifications',
                    'End dispatching Hipay notifications : '.count($deletedIds).' done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'info' => [
                    'Skipped notification : No PENDING open or in_progress refund found for the transaction '.$entity->getOrderTransactionId(),
                ],
            ],
            $logs
        );

        $this->assertSame(
            [],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertSame(
            [],
            $customFields
        );
    }

    public function provideDispatchNotificationWithoutAuthorize()
    {
        return [
            [NotificationService::PROCESS_AFTER_AUTHORIZE, TransactionStatus::CAPTURE_REQUESTED, [], OrderTransactionStates::STATE_OPEN, 'process', 'AUTHORIZED'],
            [NotificationService::PAY_PARTIALLY, TransactionStatus::PARTIALLY_CAPTURED, [], OrderTransactionStates::STATE_OPEN, 'payPartially', 'AUTHORIZED'],
            [NotificationService::PAID, TransactionStatus::CAPTURED, [], OrderTransactionStates::STATE_OPEN, 'paid', 'AUTHORIZED'],
            [NotificationService::FAILED, TransactionStatus::CAPTURE_REFUSED, [], OrderTransactionStates::STATE_OPEN, 'fail', 'AUTHORIZED'],
            [NotificationService::REFUNDED_PARTIALLY, TransactionStatus::PARTIALLY_REFUNDED, [], OrderTransactionStates::STATE_OPEN, 'refundPartially', 'CAPTURED'],
            [NotificationService::REFUNDED, TransactionStatus::REFUNDED, [], OrderTransactionStates::STATE_OPEN, 'refund', 'CAPTURED'],
        ];
    }

    /**
     * @dataProvider provideDispatchNotificationWithoutAuthorize
     */
    public function testDispatchNotificationWithoutAuthorize($notificationStatus, $hipayStatus, $previousHipayStatus, $initialState, $methodUnexpected, $message)
    {
        // Transaction
        $initialCustomFields = ['hipay_status' => $previousHipayStatus];
        $transaction = $this->generateTransaction($initialState, $initialCustomFields);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        $customFields = [];
        $captures = [];
        $transactionRepo->method('update')->willReturnCallback(function ($args) use (&$customFields, &$captures) {
            $args = current($args);
            if (isset($args['customFields'])) {
                $customFields = [$args];
            } elseif (isset($args['orderTransactionCapture'])) {
                $captures = [$args];
            }

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $operationId = Uuid::uuid4();

        // Notifications
        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus($notificationStatus);
        $entity->setData([
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

        /** @var EntityRepository&MockObject $machineStateRepo */
        $machineStateRepo = $this->createMock(EntityRepository::class);

        $idState = md5(random_int(0, PHP_INT_MAX));

        /** @var IdSearchResult&MockObject $idSearchResult */
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn($idState);

        // TransactionStateHandler
        /** @var OrderTransactionStateHandler&MockObject $TransactionStateHandler */
        $TransactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $TransactionStateHandler->expects($this->never())->method($methodUnexpected);

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
            $notificationRepo,
            $transactionRepo,
            $captureRepo,
            $refundRepo,
            $machineStateRepo,
            $this->getReadHipayConfig([
                'hashStage' => 'sha256',
                'passphraseStage' => md5(random_int(0, PHP_INT_MAX)),
                'environment' => 'Stage',
            ]),
            $TransactionStateHandler,
            $logger
        );

        $service->dispatchNotifications();

        $this->assertSame(
            [
                'notice' => [
                    'Start dispatching '.$notificationCollection->count().' hipay notifications',
                    'End dispatching Hipay notifications : '.count($deletedIds).' done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'info' => [
                    'Skipped notification : No '.$message.' notification receive for the transaction '.$transaction->getId().', skip status '.$hipayStatus,
                ],
            ],
            $logs
        );

        $this->assertSame(
            [],
            $deletedIds,
            'Deleteds notifications ID missmatch'
        );

        $this->assertSame(
            [],
            $customFields
        );

        $this->assertEquals(
            [],
            $captures,
            'Captures creation missmatch'
        );
    }

    public function testDispatchNotificationStatutCodeInvalid()
    {
        $transaction = $this->generateTransaction(OrderTransactionStates::STATE_OPEN);

        /** @var EntityRepository&MockObject $transactionRepo */
        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('search')->willReturnCallback(function ($criteria, $context) use ($transaction) {
            $collection = new OrderTransactionCollection([$transaction]);

            return new EntitySearchResult(HipayNotificationEntity::class, $collection->count(), $collection, null, $criteria, $context);
        });

        /** @var EntityRepository&MockObject $notificationRepo */
        $notificationRepo = $this->createMock(EntityRepository::class);

        $entity = new HipayNotificationEntity();
        $entity->setId(md5(random_int(0, PHP_INT_MAX)));
        $entity->setOrderTransaction($transaction);
        $entity->setOrderTransactionId($transaction->getId());
        $entity->setStatus(3000);
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

        $service = new NotificationService(
            $notificationRepo,
            $transactionRepo,
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
                    'Start dispatching '.$collection->count().' hipay notifications',
                    'End dispatching Hipay notifications : 0 done',
                ],
                'debug' => [
                    'Dispatching notification '.$entity->getId().' for the transaction '.$entity->getOrderTransactionId(),
                ],
                'error' => [
                    'Error during an Hipay notification dispatching : Bad status code for Hipay notification '.$entity->getId(),
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
