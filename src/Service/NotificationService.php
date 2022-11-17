<?php

namespace HiPay\Payment\Service;

use HiPay\Fullservice\Enum\Helper\HashAlgorithm;
use HiPay\Fullservice\Enum\Transaction\TransactionStatus;
use HiPay\Fullservice\Exception\ApiErrorException;
use HiPay\Fullservice\Exception\UnexpectedValueException;
use HiPay\Fullservice\Helper\Signature;
use HiPay\Payment\Core\Checkout\Payment\HipayNotificationEntity;
use HiPay\Payment\Exception\SkipNotificationException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class NotificationService
{
    private ReadHipayConfigService $config;

    private EntityRepository $notificationRepo;

    private EntityRepository $transactionRepo;

    private EntityRepository $captureRepo;

    private EntityRepository $refundRepo;

    private EntityRepository $stateMachineRepo;

    private OrderTransactionStateHandler $orderTransactionStateHandler;

    private LoggerInterface $logger;

    /**
     * Hipay notification Status
     * Order is important.
     */
    public const PROCESS = 1;
    public const FAILED = 2;
    public const CHARGEDBACK = 3;
    public const AUTHORIZE = 4;
    public const PROCESS_AFTER_AUTHORIZE = 5;
    public const PAY_PARTIALLY = 6;
    public const PAID = 7;
    public const REFUNDED_PARTIALLY = 8;
    public const REFUNDED = 9;
    public const CANCELLED = 10;

    /**
     * Correspondence between OrderTransactionState and Hipay status.
     *
     * @var array<int,string>
     */
    public const CONVERT_STATE = [
        self::PROCESS => OrderTransactionStates::STATE_IN_PROGRESS,
        self::FAILED => OrderTransactionStates::STATE_FAILED,
        self::CHARGEDBACK => OrderTransactionStates::STATE_CHARGEBACK,
        self::AUTHORIZE => OrderTransactionStates::STATE_AUTHORIZED,
        self::PROCESS_AFTER_AUTHORIZE => OrderTransactionStates::STATE_IN_PROGRESS,
        self::PAY_PARTIALLY => OrderTransactionStates::STATE_PARTIALLY_PAID,
        self::PAID => OrderTransactionStates::STATE_PAID,
        self::REFUNDED_PARTIALLY => OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
        self::REFUNDED => OrderTransactionStates::STATE_REFUNDED,
        self::CANCELLED => OrderTransactionStates::STATE_CANCELLED,
    ];

    public function __construct(
        EntityRepository $hipayNotificationRepository,
        EntityRepository $transactionRepository,
        EntityRepository $captureRepository,
        EntityRepository $refundRepository,
        EntityRepository $stateMachineRepository,
        ReadHipayConfigService $config,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        LoggerInterface $hipayNotificationLogger
    ) {
        $this->notificationRepo = $hipayNotificationRepository;
        $this->transactionRepo = $transactionRepository;
        $this->captureRepo = $captureRepository;
        $this->refundRepo = $refundRepository;
        $this->stateMachineRepo = $stateMachineRepository;
        $this->config = $config;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->logger = $hipayNotificationLogger;
    }

    /**
     * Save the received notification request.
     */
    public function saveNotificationRequest(Request $request): void
    {
        if (!$this->validateRequest($request)) {
            throw new AccessDeniedException('Signature does not match');
        }

        if (!$notificationDate = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sO', $request->get('date_updated'))) {
            throw new MissingMandatoryParametersException('date_updated is mandatory');
        }

        if (!$orderTransactionId = ($request->request->get('order')['id'] ?? null)) {
            throw new MissingMandatoryParametersException('order.id is mandatory');
        }

        if (!$transactionReference = $request->request->get('transaction_reference')) {
            throw new MissingMandatoryParametersException('transaction_reference is mandatory');
        }
        $notification = [
            'status' => $this->getStatus($request->request->getInt('status')),
            'data' => $request->request->all(),
            'notificationUpdatedAt' => $notificationDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'orderTransaction' => [
                'id' => $orderTransactionId,
                'customFields' => [
                    'hipay_transaction_reference' => $transactionReference,
                ],
            ],
        ];

        $this->notificationRepo->create([$notification], Context::createDefaultContext());
    }

    /**
     * Validate the request came from Hipay.
     *
     * @throws InvalidSettingValueException
     * @throws ApiErrorException
     */
    private function validateRequest(Request $request): bool
    {
        $algos = [
            'sha256' => HashAlgorithm::SHA256,
            'sha512' => HashAlgorithm::SHA512,
        ];

        if (!isset($algos[$this->config->getHash()])) {
            throw new ApiErrorException('Bad configuration unknown algorythm "'.$this->config->getHash().'"');
        }

        if (!$signature = $request->headers->get('x-allopass-signature', null)) {
            throw new UnauthorizedHttpException('header', 'Missing signature header');
        }

        return Signature::isValidHttpSignature(
            $this->config->getPassphrase(),
            $algos[$this->config->getHash()],
            $signature,
            (string) $request->getContent()
        );
    }

    /**
     * Convert the code status into shopware compatible status.
     *
     * @return int
     *
     * @throws \Exception
     */
    private function getStatus(int $codeStatus)
    {
        switch ($codeStatus) {
            // Failed
            case TransactionStatus::AUTHENTICATION_FAILED:
            case TransactionStatus::BLOCKED:
            case TransactionStatus::DENIED:
            case TransactionStatus::REFUSED:
            case TransactionStatus::EXPIRED:
            case 134: // Dispute lost
            case TransactionStatus::REFUND_REFUSED:
            case TransactionStatus::CAPTURE_REFUSED:
            case 178: // Soft decline
                $status = static::FAILED;
                break;
                // Cancelled
            case TransactionStatus::CANCELLED:
            case 143: // Authorization cancelled
            case TransactionStatus::AUTHORIZATION_CANCELLATION_REQUESTED:
                $status = static::CANCELLED;
                break;
                // In progress
            case TransactionStatus::AUTHORIZED_AND_PENDING:
            case TransactionStatus::AUTHORIZATION_REQUESTED:
            case 144: // Reference rendered
            case 169: // Credit requested
            case 172: // In progress
            case 174: // Awaiting Terminal
            case 177: // Challenge requested
            case 200: // Pending Payment
                $status = static::PROCESS;
                break;
                // chargedback
            case TransactionStatus::CHARGED_BACK:
                $status = static::CHARGEDBACK;
                break;
                // Authorized
            case TransactionStatus::AUTHORIZED:
                $status = static::AUTHORIZE;
                break;
                // Payment in progress
            case TransactionStatus::CAPTURE_REQUESTED:
            case TransactionStatus::REFUND_REQUESTED:
                $status = static::PROCESS_AFTER_AUTHORIZE;
                break;
                // Paid partially
            case TransactionStatus::PARTIALLY_CAPTURED:
                $status = static::PAY_PARTIALLY;
                break;
                // Paid
            case TransactionStatus::CAPTURED:
            case 166: // Debited (cardholder credit)
            case 168: // Debited (cardholder credit)
                $status = static::PAID;
                break;
                // Refunded (Partially)
            case TransactionStatus::PARTIALLY_REFUNDED:
                $status = static::REFUNDED_PARTIALLY;
                break;
                // Refunded
            case TransactionStatus::REFUNDED:
                $status = static::REFUNDED;
                break;
            default:
                throw new UnexpectedValueException('Status code "'.$codeStatus.'" invalid');
        }

        return $status;
    }

    /**
     * Dispatch hipay notifications into order transactions.
     */
    public function dispatchNotifications(): void
    {
        try {
            $notifications = $this->getActiveHipayNotifications();

            $this->logger->notice('Start dispatching '.$notifications->count().' hipay notifications');

            $notificationIds = [];

            /** @var HipayNotificationEntity $notification */
            foreach ($notifications as $notification) {
                try {
                    $this->handleNotification($notification);
                    $notificationIds[$notification->getId()] = ['id' => $notification->getId()];
                } catch (SkipNotificationException $e) {
                    $this->logger->info('Skipped notification : '.$e->getMessage());
                } catch (\Throwable $e) {
                    $this->logger->error('Error during an Hipay notification dispatching : '.$e->getMessage());
                }
            }

            $this->notificationRepo->delete(array_values($notificationIds), Context::createDefaultContext());

            $this->logger->notice('End dispatching Hipay notifications : '.count($notificationIds).' done');
        } catch (\Throwable $e) {
            $this->logger->error('Error during Hipay notifications dispatching : '.$e->getMessage());
        }
    }

    /**
     * handle a notification and return the id when valid.
     */
    private function handleNotification(HipayNotificationEntity $notification): void
    {
        $context = Context::createDefaultContext();

        $data = $notification->getData();

        $hipayStatus = $data['status'];

        /** @var OrderTransactionEntity $transaction */
        $transaction = $this->transactionRepo->search(new Criteria([$notification->getOrderTransactionId()]), $context)->first();

        $this->logger->debug('Dispatching notification '.$notification->getId().' for the transaction '.$transaction->getId());

        if (!isset(static::CONVERT_STATE[$notification->getStatus()])) {
            throw new UnexpectedValueException('Bad status code for Hipay notification '.$notification->getId());
        }

        $stateMachine = $transaction->getStateMachineState()->getTechnicalName();
        if ($stateMachine === static::CONVERT_STATE[$notification->getStatus()]) {
            // The transaction have already the state of the notification
            $this->logger->info(
                'Ignore notification '.$notification->getId().'. '
                .'Transaction '.$transaction->getId().' already have status '.$stateMachine
            );

            return;
        }

        $statutChange = false;

        switch ($notification->getStatus()) {
            case static::PROCESS:
                $this->orderTransactionStateHandler->process($transaction->getId(), $context);
                $statutChange = true;
                break;

            case static::FAILED:
                $statutChange = $this->handleFailedNotification($notification, $transaction);

                break;

            case static::CHARGEDBACK:
                $this->orderTransactionStateHandler->chargeback($transaction->getId(), $context);
                $statutChange = true;
                break;

            case static::AUTHORIZE:
                $this->orderTransactionStateHandler->authorize($transaction->getId(), $context);
                $statutChange = true;
                break;

            case static::PROCESS_AFTER_AUTHORIZE:
            case static::PAY_PARTIALLY:
            case static::PAID:
                $statutChange = $this->handleAuthorizedNotification($notification, $transaction);
                break;

            case static::REFUNDED_PARTIALLY:
            case static::REFUNDED:
                $statutChange = $this->handleCapturedNotification($notification, $transaction);
                break;

            case static::CANCELLED:
                $this->orderTransactionStateHandler->cancel($transaction->getId(), $context);
                $statutChange = true;
                break;
        }

        $this->addTransactionHipayStatus($transaction, $hipayStatus);

        if ($statutChange) {
            $this->logger->info(
                'Change order transaction '.$transaction->getId()
                .' to status '.static::CONVERT_STATE[$notification->getStatus()].' (previously '.$stateMachine.')'
            );
        }
    }

    /**
     * Handle fail notification.
     */
    private function handleFailedNotification(HipayNotificationEntity $notification, OrderTransactionEntity $transaction): bool
    {
        $data = $notification->getData();

        $hipayStatus = $data['status'];
        $operationId = $data['operation_id'] ?? $data['custom_data']['operation_id'];

        if (TransactionStatus::REFUND_REFUSED == $hipayStatus) {
            $this->checkPreviousStatus($hipayStatus, [TransactionStatus::CAPTURED], $transaction);

            if (!$refundId = $this->findRefundId($operationId)) {
                throw new SkipNotificationException('No PENDING open or in_progress refund found for the transaction '.$transaction->getId());
            }

            $this->updateRefund($refundId, OrderTransactionCaptureRefundStates::STATE_FAILED);
        }

        if (TransactionStatus::CAPTURE_REFUSED == $hipayStatus) {
            $this->checkPreviousStatus($hipayStatus, [TransactionStatus::AUTHORIZED, TransactionStatus::CAPTURE_REQUESTED], $transaction);

            if (!$captureId = $this->getCaptureId($operationId, OrderTransactionCaptureStates::STATE_PENDING, $transaction->getId())) {
                throw new SkipNotificationException('No PENDING capture found for the transaction '.$transaction->getId());
            }

            $this->saveCapture($captureId, $data['captured_amount'], $operationId, $transaction, OrderTransactionCaptureStates::STATE_FAILED);
        }

        $this->orderTransactionStateHandler->fail($transaction->getId(), Context::createDefaultContext());

        return true;
    }

    /**
     * Handle notification who need AUTHORIZED notification.
     */
    private function handleAuthorizedNotification(HipayNotificationEntity $notification, OrderTransactionEntity $transaction): bool
    {
        $data = $notification->getData();

        $hipayStatus = $data['status'];
        $operationId = $data['operation_id'] ?? $data['custom_data']['operation_id'];
        $capturedAmount = $data['captured_amount'];

        $this->checkPreviousStatus($hipayStatus, [TransactionStatus::AUTHORIZED], $transaction);

        $captureId = $this->getCaptureId($operationId, OrderTransactionCaptureStates::STATE_PENDING, $transaction->getId());

        if ($notification->getStatus() === static::PROCESS_AFTER_AUTHORIZE) {
            if ($captureId) {
                $this->logger->info('Ignore notification '.$notification->getId().'. Transaction '.$transaction->getId().' capture already PENDING');
            } else {
                $this->logger->info('Notification '.$notification->getId().' create PENDING capture for the transaction '.$transaction->getId());
                $this->saveCapture(null, $capturedAmount, $operationId, $transaction, OrderTransactionCaptureStates::STATE_PENDING);
            }

            return false;
        }

        if (!$captureId) {
            throw new SkipNotificationException('No PENDING capture found for the transaction '.$transaction->getId());
        }

        switch ($notification->getStatus()) {
            case static::PAY_PARTIALLY:
                $this->saveCapture($captureId, $capturedAmount, $operationId, $transaction, OrderTransactionCaptureStates::STATE_COMPLETED);
                $this->orderTransactionStateHandler->payPartially($transaction->getId(), Context::createDefaultContext());
                break;

            case static::PAID:
                $this->saveCapture($captureId, $capturedAmount, $operationId, $transaction, OrderTransactionCaptureStates::STATE_COMPLETED);
                $this->orderTransactionStateHandler->paid($transaction->getId(), Context::createDefaultContext());
                break;
        }

        return true;
    }

    /**
     * Handle notification who need CAPTURED notification.
     */
    private function handleCapturedNotification(HipayNotificationEntity $notification, OrderTransactionEntity $transaction): bool
    {
        $data = $notification->getData();

        $hipayStatus = $data['status'];
        $operationId = $data['operation_id'] ?? $data['custom_data']['operation_id'];

        $this->checkPreviousStatus($hipayStatus, [TransactionStatus::CAPTURED], $transaction);

        if (!$refundId = $this->findRefundId($operationId)) {
            throw new SkipNotificationException('No PENDING open or in_progress refund found for the transaction '.$transaction->getId());
        }

        $this->updateRefund($refundId, OrderTransactionCaptureREfundStates::STATE_COMPLETED);

        switch ($notification->getStatus()) {
            case static::REFUNDED_PARTIALLY:
                $this->orderTransactionStateHandler->refundPartially($transaction->getId(), Context::createDefaultContext());
                break;

            case static::REFUNDED:
                $this->orderTransactionStateHandler->refund($transaction->getId(), Context::createDefaultContext());
                break;
        }

        return true;
    }

    /**
     * Add order Transaction capture.
     */
    private function saveCapture(?string $captureId, float $amount, string $operationId, OrderTransactionEntity $transaction, string $state): void
    {
        $captureData = [
            'id' => $captureId,
            'orderTransactionId' => $transaction->getId(),
            'externalReference' => $operationId,
            'stateId' => $this->getMachineStateId($state, OrderTransactionCaptureStates::STATE_MACHINE),
            'totalAmount' => $amount,
            'amount' => new CalculatedPrice(
                $amount,
                $amount,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            ),
        ];

        if (!$captureId) {
            $this->captureRepo->create([$captureData], Context::createDefaultContext());
        } else {
            $this->captureRepo->update([$captureData], Context::createDefaultContext());
        }
    }

    /**
     * Find an open or in_progress refundId from an operation Id.
     */
    private function findRefundId(string $operationId): ?string
    {
        $classState = OrderTransactionCaptureRefundStates::class;

        $criteria = new Criteria();
        $criteria
            ->setLimit(1)
            ->addFilter(new EqualsFilter('externalReference', $operationId))
            ->addFilter(
                new OrFilter([
                    new EqualsFilter(
                        'stateId',
                        $this->getMachineStateId($classState::STATE_IN_PROGRESS, $classState::STATE_MACHINE)
                    ),
                    new EqualsFilter(
                        'stateId',
                        $this->getMachineStateId($classState::STATE_OPEN, $classState::STATE_MACHINE)
                    ),
                ])
            );

        return $this->refundRepo->searchIds($criteria, Context::createDefaultContext())->firstId();
    }

    /**
     * Update a giving OrderTransactionCaptureRefund state.
     */
    private function updateRefund(string $refundId, string $state): void
    {
        $this->refundRepo->update([
            [
                'id' => $refundId,
                'stateId' => $this->getMachineStateId($state, OrderTransactionCaptureRefundStates::STATE_MACHINE),
            ],
        ], Context::createDefaultContext());
    }

    /**
     * Retreive a transaction capture machine statebased on name.
     */
    private function getMachineStateId(string $state, string $technicalName): string
    {
        $criteria = new Criteria();
        $criteria
            ->setLimit(1)
            ->addFilter(new EqualsFilter('technicalName', $state))
            ->addFilter(new EqualsFilter('stateMachine.technicalName', $technicalName));

        return $this->stateMachineRepo->searchIds($criteria, Context::createDefaultContext())->firstId();
    }

    /**
     * Retreive a caputre ID based on the operationId and machine state.
     */
    private function getCaptureId(string $operationId, string $state, string $transactionId): ?string
    {
        $criteria = new Criteria();
        $criteria
            ->setLimit(1)
            ->addFilter(new EqualsFilter('externalReference', $operationId))
            ->addFilter(new EqualsFilter('orderTransactionId', $transactionId))
            ->addFilter(new EqualsFilter('stateId', $this->getMachineStateId($state, OrderTransactionCaptureStates::STATE_MACHINE)));

        return $this->captureRepo->searchIds($criteria, Context::createDefaultContext())->firstId();
    }

    /**
     * Retreive hipay notification to handle.
     */
    private function getActiveHipayNotifications(): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria
            ->addSorting(new FieldSorting('status', FieldSorting::ASCENDING))
            ->addAssociations(['orderTransaction'])
        ;

        return $this->notificationRepo->search($criteria, Context::createDefaultContext());
    }

    /**
     * Get the hipay status assigned to the order transaction.
     *
     * @return int[]
     */
    private function getTransactionHipayStatus(OrderTransactionEntity $transaction): array
    {
        $transactionHipayStatus = [];

        if ($customFields = $transaction->getCustomFields()) {
            $transactionHipayStatus = $customFields['hipay_status'] ?? [];
        }

        return $transactionHipayStatus;
    }

    /**
     * Add received hipay status to the order transaction.
     */
    private function addTransactionHipayStatus(OrderTransactionEntity $transaction, int $hipayStatus): void
    {
        $this->transactionRepo->update([
            [
                'id' => $transaction->getId(),
                'customFields' => [
                    'hipay_status' => array_merge(
                        $this->getTransactionHipayStatus($transaction),
                        [$hipayStatus]
                    ),
                ],
            ],
        ], Context::createDefaultContext());
    }

    /**
     * Check if the transaction have the previous status.
     *
     * @param int[] $statusRequired
     */
    private function checkPreviousStatus(int $currentStatus, array $statusRequired, OrderTransactionEntity $transaction): void
    {
        $previousHipayStatus = $this->getTransactionHipayStatus($transaction);

        $reflectionClass = new \ReflectionClass(TransactionStatus::class);
        $constants = array_flip($reflectionClass->getConstants());

        foreach ($statusRequired as $status) {
            if (!in_array($status, $previousHipayStatus)) {
                throw new SkipNotificationException('No '.$constants[$status].' notification receive for the transaction '.$transaction->getId().', skip status '.$currentStatus);
            }
        }
    }
}
