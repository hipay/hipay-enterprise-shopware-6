<?php

namespace HiPay\Payment\Service;

use HiPay\Fullservice\Enum\Helper\HashAlgorithm;
use HiPay\Fullservice\Enum\Transaction\TransactionStatus;
use HiPay\Fullservice\Exception\ApiErrorException;
use HiPay\Fullservice\Exception\UnexpectedValueException;
use HiPay\Fullservice\Helper\Signature;
use HiPay\Payment\Core\Checkout\Payment\Capture\OrderCaptureEntity;
use HiPay\Payment\Core\Checkout\Payment\HipayNotification\HipayNotificationEntity;
use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderEntity;
use HiPay\Payment\Core\Checkout\Payment\Refund\OrderRefundEntity;
use HiPay\Payment\Enum\CaptureStatus;
use HiPay\Payment\Enum\RefundStatus;
use HiPay\Payment\Exception\ExpiredNotificationException;
use HiPay\Payment\Exception\SkipNotificationException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;

class NotificationService
{
    private ReadHipayConfigService $config;

    private EntityRepository $transactionRepo;

    private EntityRepository $notificationRepo;

    private EntityRepository $hipayOrderRepo;

    private EntityRepository $hipayOrderCaptureRepo;

    private EntityRepository $hipayOrderRefundRepo;

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
    public const PROCESS_AFTER_CAPTURE = 8;
    public const REFUNDED_PARTIALLY = 9;
    public const REFUNDED = 10;
    public const CANCELLED = 11;

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
        self::PROCESS_AFTER_CAPTURE => OrderTransactionStates::STATE_IN_PROGRESS,
        self::REFUNDED_PARTIALLY => OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
        self::REFUNDED => OrderTransactionStates::STATE_REFUNDED,
        self::CANCELLED => OrderTransactionStates::STATE_CANCELLED,
    ];

    public function __construct(
        EntityRepository $transactionRepository,
        EntityRepository $hipayNotificationRepository,
        EntityRepository $hipayOrderRepository,
        EntityRepository $hipayOrderCaptureRepository,
        EntityRepository $hipayOrderRefundRepository,
        ReadHipayConfigService $config,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        LoggerInterface $hipayNotificationLogger
    ) {
        $this->transactionRepo = $transactionRepository;
        $this->notificationRepo = $hipayNotificationRepository;
        $this->hipayOrderRepo = $hipayOrderRepository;
        $this->hipayOrderCaptureRepo = $hipayOrderCaptureRepository;
        $this->hipayOrderRefundRepo = $hipayOrderRefundRepository;
        $this->config = $config;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->logger = $hipayNotificationLogger;
    }

    /**
     * Save the received notification request.
     */
    public function saveNotificationRequest(Request $request): void
    {
        $context = Context::createDefaultContext();

        if (!$this->validateRequest($request)) {
            throw new AccessDeniedException('Signature does not match');
        }

        if (!$notificationDate = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sO', $request->get('date_updated'))) {
            throw new MissingMandatoryParametersException('date_updated is mandatory');
        }

        if (!$orderTransactionId = ($request->request->get('order')['id'] ?? null)) {
            throw new MissingMandatoryParametersException('order.id is mandatory');
        }

        if (!$transactionReference = $request->request->getAlnum('transaction_reference')) {
            throw new MissingMandatoryParametersException('transaction_reference is mandatory');
        }

        /** @var OrderTransactionEntity|null $transaction */
        $transaction = $this->transactionRepo->search((new Criteria([$orderTransactionId]))->addAssociation('order'), $context)->first();
        if (!$transaction) {
            throw new NotFoundResourceException('Transaction not found with order ID '.$orderTransactionId);
        }

        // Create or update if exists a HiPay order related to this transaction to database
        $hipayOrderCriteria = (new Criteria())->addFilter(new EqualsFilter('transactionReference', $transactionReference));

        if (!$hipayOrder = $this->getAssociatedHiPayOrder($hipayOrderCriteria)) {
            $hipayOrder = HipayOrderEntity::create($transactionReference, $transaction->getOrder(), $transaction);
            $this->hipayOrderRepo->create([$hipayOrder->toArray()], $context);
            /**
             * Retrieve hipayOrder after creation.
             *
             * @var HipayOrderEntity
             */
            $hipayOrder = $this->getAssociatedHiPayOrder($hipayOrderCriteria);
        } else {
            $hipayOrder->setTransanctionReference($transactionReference);
            $hipayOrder->setOrder($transaction->getOrder());
            $hipayOrder->setTransaction($transaction);
            $this->hipayOrderRepo->update([$hipayOrder->toArray()], $context);
        }

        // Create notification to database
        $notification = HipayNotificationEntity::create(
            $this->getStatus($request->request->getInt('status'), $request->request),
            $request->request->all(),
            new \DateTime($notificationDate->format(Defaults::STORAGE_DATE_TIME_FORMAT)),
            $hipayOrder
        );
        $this->notificationRepo->create([$notification->toArray()], $context);
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
     * @throws UnexpectedValueException
     */
    private function getStatus(int $codeStatus, InputBag $request): int
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
                return static::FAILED;
                // Cancelled
            case TransactionStatus::CANCELLED:
            case 143: // Authorization cancelled
            case TransactionStatus::AUTHORIZATION_CANCELLATION_REQUESTED:
                return static::CANCELLED;
                // In progress
            case TransactionStatus::AUTHORIZED_AND_PENDING:
            case TransactionStatus::AUTHORIZATION_REQUESTED:
            case 144: // Reference rendered
            case 169: // Credit requested
            case 172: // In progress
            case 174: // Awaiting Terminal
            case 177: // Challenge requested
            case 200: // Pending Payment
                return static::PROCESS;
                // chargedback
            case TransactionStatus::CHARGED_BACK:
                return static::CHARGEDBACK;
                // Authorized
            case TransactionStatus::AUTHORIZED:
                return static::AUTHORIZE;
                // Capture requested
            case TransactionStatus::CAPTURE_REQUESTED:
                return static::PROCESS_AFTER_AUTHORIZE;
                // Refund requested
            case TransactionStatus::REFUND_REQUESTED:
                return static::PROCESS_AFTER_CAPTURE;
                // Paid partially
            case TransactionStatus::PARTIALLY_CAPTURED:
                return static::PAY_PARTIALLY;
                // Paid
            case TransactionStatus::CAPTURED:
                if (floatval($request->get('captured_amount')) < floatval($request->get('authorized_amount'))) {
                    return static::PAY_PARTIALLY;
                } else {
                    return static::PAID;
                }
                // no break
            case 166: // Debited (cardholder credit)
            case 168: // Debited (cardholder credit)
                return static::PAID;
                // Refunded (Partially)
            case TransactionStatus::PARTIALLY_REFUNDED:
                return static::REFUNDED_PARTIALLY;
                // Refunded
            case TransactionStatus::REFUNDED:
                if (floatval($request->get('refunded_amount')) < floatval($request->get('captured_amount'))) {
                    return static::REFUNDED_PARTIALLY;
                } else {
                    return static::REFUNDED;
                }
                // no break
            default:
                throw new UnexpectedValueException('Status code "'.$codeStatus.'" invalid');
        }
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
                $notificationId = $notification->getId();
                try {
                    $this->handleNotification($notification);
                    $notificationIds[] = ['id' => $notificationId];
                } catch (SkipNotificationException $e) {
                    $this->logger->info('Skipped notification : '.$e->getMessage());
                } catch (ExpiredNotificationException $e) {
                    $this->logger->warning($e->getMessage());
                    $notificationIds[] = ['id' => $notificationId];
                } catch (\Throwable $e) {
                    $this->logger->error('Error during an Hipay notification dispatching : '.$e->getMessage());
                }
            }

            $this->notificationRepo->delete($notificationIds, Context::createDefaultContext());

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
        // Do not process expired notifications
        if ($notification->getCreatedAt()->diff(new \DateTime())->days >= 1) {
            throw new ExpiredNotificationException('Notification '.$notification->getId().' expired after 1 day');
        }

        $context = Context::createDefaultContext();

        $data = $notification->getData();

        $hipayStatus = $data['status'];

        /** @var HipayOrderEntity */
        $hipayOrder = $this->getAssociatedHiPayOrder(
            (new Criteria([$notification->getHipayOrderId()]))->addAssociations(['transaction', 'captures', 'refunds'])
        );

        $this->logger->debug('Dispatching notification '.$notification->getId().' for the transaction '.$hipayOrder->getTransactionId());

        if (!isset(static::CONVERT_STATE[$notification->getStatus()])) {
            throw new UnexpectedValueException('Bad status code for Hipay notification '.$notification->getId());
        }

        $stateMachine = $hipayOrder->getTransaction()->getStateMachineState()->getTechnicalName();
        $statutChange = false;

        switch ($notification->getStatus()) {
            case static::PROCESS:
                $this->orderTransactionStateHandler->process($hipayOrder->getTransactionId(), $context);
                $statutChange = true;
                break;

            case static::FAILED:
                $statutChange = $this->handleFailedNotification($notification, $hipayOrder);
                break;

            case static::CHARGEDBACK:
                $this->orderTransactionStateHandler->chargeback($hipayOrder->getTransactionId(), $context);
                $statutChange = true;
                break;

            case static::AUTHORIZE:
                $this->orderTransactionStateHandler->authorize($hipayOrder->getTransactionId(), $context);
                $statutChange = true;
                break;

            case static::PROCESS_AFTER_AUTHORIZE:
            case static::PAY_PARTIALLY:
            case static::PAID:
                $statutChange = $this->handleAuthorizedNotification($notification, $hipayOrder);
                break;

            case static::PROCESS_AFTER_CAPTURE:
            case static::REFUNDED_PARTIALLY:
            case static::REFUNDED:
                $statutChange = $this->handleCapturedNotification($notification, $hipayOrder);
                break;

            case static::CANCELLED:
                $this->orderTransactionStateHandler->cancel($hipayOrder->getTransactionId(), $context);
                $statutChange = true;
                break;
        }

        $this->addTransactionHipayStatus($hipayOrder, $hipayStatus);

        if ($statutChange) {
            $this->logger->info(
                'Change order transaction '.$hipayOrder->getTransactionId()
                .' to status '.static::CONVERT_STATE[$notification->getStatus()].' (previously '.$stateMachine.')'
            );
        }
    }

    /**
     * Handle fail notification.
     */
    private function handleFailedNotification(HipayNotificationEntity $notification, HipayOrderEntity $hipayOrder): bool
    {
        $data = $notification->getData();

        $hipayStatus = $data['status'];

        if (TransactionStatus::REFUND_REFUSED == $hipayStatus) {
            $operationId = $this->getOperationId($data);
            $this->checkAllPreviousStatus($hipayStatus, [TransactionStatus::CAPTURED, TransactionStatus::REFUND_REQUESTED], $hipayOrder);

            if (!$refund = $hipayOrder->getRefunds()->getRefundByOperationId($operationId, RefundStatus::IN_PROGRESS)) {
                throw new SkipNotificationException('No IN_PROGRESS refund found with operation ID '.$operationId.' for the transaction '.$hipayOrder->getTransactionId());
            }

            $this->saveRefund(RefundStatus::FAILED, $refund);
        } elseif (TransactionStatus::CAPTURE_REFUSED == $hipayStatus) {
            $operationId = $this->getOperationId($data);
            $this->checkAllPreviousStatus($hipayStatus, [TransactionStatus::AUTHORIZED, TransactionStatus::CAPTURE_REQUESTED], $hipayOrder);

            if (!$capture = $hipayOrder->getCaptures()->getCaptureByOperationId($operationId, CaptureStatus::IN_PROGRESS)) {
                throw new SkipNotificationException('No IN_PROGRESS capture found with operation ID '.$operationId.' for the transaction '.$hipayOrder->getTransactionId());
            }

            $this->saveCapture(CaptureStatus::FAILED, $capture);
        }

        $this->orderTransactionStateHandler->fail($hipayOrder->getTransactionId(), Context::createDefaultContext());

        return true;
    }

    /**
     * Handle notification who need AUTHORIZED notification.
     */
    private function handleAuthorizedNotification(HipayNotificationEntity $notification, HipayOrderEntity $hipayOrder): bool
    {
        $context = Context::createDefaultContext();
        $data = $notification->getData();

        $hipayStatus = $data['status'];
        $operationId = $this->getOperationId($data);
        $capturedAmount = $data['operation']['amount'] ?? $data['captured_amount'];

        $this->checkAllPreviousStatus($hipayStatus, [TransactionStatus::AUTHORIZED], $hipayOrder);

        $capture = $hipayOrder->getCaptures()->getCaptureByOperationId($operationId);

        if ($notification->getStatus() === static::PROCESS_AFTER_AUTHORIZE) {
            if ($capture && CaptureStatus::IN_PROGRESS === $capture->getStatus()) {
                $this->logger->info('Ignore notification '.$notification->getId().'. Capture '.$capture->getOperationId().' already in progress');
            } else {
                if (!$capture) {
                    $this->logger->info('Notification '.$notification->getId().' create IN_PROGRESS capture for the transaction '.$hipayOrder->getTransactionId());
                } else {
                    $this->logger->info('Notification '.$notification->getId().' update capture '.$capture->getOperationId().' to IN_PROGRESS status for the transaction '.$hipayOrder->getTransactionId());
                }
                $this->saveCapture(CaptureStatus::IN_PROGRESS, $capture, $capturedAmount, $operationId, $hipayOrder);
            }

            return false;
        }

        if (!$capture) {
            throw new SkipNotificationException('No capture found with operation ID '.$operationId.' for the transaction '.$hipayOrder->getTransactionId());
        }

        $this->saveCapture(CaptureStatus::COMPLETED, $capture);

        switch ($notification->getStatus()) {
            case static::PAY_PARTIALLY:
                $this->orderTransactionStateHandler->payPartially($hipayOrder->getTransactionId(), $context);
                break;

            case static::PAID:
                if ($hipayOrder->getTransaction()->getStateMachineState()->getTechnicalName() === static::CONVERT_STATE[static::PAY_PARTIALLY]) {
                    // Transition to IN_PROGRESS before PAID because Shopware cannot change status from paid_partially to paid
                    // Issue : https://issues.shopware.com/issues/NEXT-22317
                    $this->orderTransactionStateHandler->process($hipayOrder->getTransactionId(), $context);
                }
                $this->orderTransactionStateHandler->paid($hipayOrder->getTransactionId(), $context);
                break;
        }

        return true;
    }

    /**
     * Handle notification who need CAPTURED notification.
     */
    private function handleCapturedNotification(HipayNotificationEntity $notification, HipayOrderEntity $hipayOrder): bool
    {
        $context = Context::createDefaultContext();
        $data = $notification->getData();

        $hipayStatus = $data['status'];
        $operationId = $this->getOperationId($data);
        $refundedAmount = $data['operation']['amount'] ?? $data['refunded_amount'];

        $this->checkOnePreviousStatus($hipayStatus, [TransactionStatus::CAPTURED, TransactionStatus::PARTIALLY_CAPTURED], $hipayOrder);

        $refund = $hipayOrder->getRefunds()->getRefundByOperationId($operationId);

        if ($notification->getStatus() === static::PROCESS_AFTER_CAPTURE) {
            if ($refund && RefundStatus::IN_PROGRESS === $refund->getStatus()) {
                $this->logger->info('Ignore notification '.$notification->getId().'. Refund '.$refund->getOperationId().' already in progress');
            } else {
                if (!$refund) {
                    $this->logger->info('Notification '.$notification->getId().' create IN_PROGRESS refund for the transaction '.$hipayOrder->getTransactionId());
                } else {
                    $this->logger->info('Notification '.$notification->getId().' update refund '.$refund->getOperationId().' to IN_PROGRESS status for the transaction '.$hipayOrder->getTransactionId());
                }
                $this->saveRefund(RefundStatus::IN_PROGRESS, $refund, $refundedAmount, $operationId, $hipayOrder);
            }

            return false;
        }

        if (!$refund) {
            throw new SkipNotificationException('No refund found with operation ID '.$operationId.' for the transaction '.$hipayOrder->getTransactionId());
        }

        $this->saveRefund(RefundStatus::COMPLETED, $refund);

        switch ($notification->getStatus()) {
            case static::REFUNDED_PARTIALLY:
                $this->orderTransactionStateHandler->refundPartially($hipayOrder->getTransactionId(), $context);
                break;

            case static::REFUNDED:
                $this->orderTransactionStateHandler->refund($hipayOrder->getTransactionId(), $context);
                break;
        }

        return true;
    }

    /**
     * Add order Transaction capture.
     */
    private function saveCapture(string $status, ?OrderCaptureEntity $capture, ?float $amount = null, ?string $operationId = null, ?HipayOrderEntity $hipayOrder = null): void
    {
        $context = Context::createDefaultContext();
        if (!$capture) {
            $capture = OrderCaptureEntity::create($operationId, $amount, $hipayOrder, $status);
            $this->hipayOrderCaptureRepo->create([$capture->toArray()], $context);
        } else {
            $capture->setStatus($status);
            $this->hipayOrderCaptureRepo->update([$capture->toArray()], $context);
        }
    }

    /**
     * Add order Transaction refund.
     */
    private function saveRefund(string $status, ?OrderRefundEntity $refund, ?float $amount = null, ?string $operationId = null, ?HipayOrderEntity $hipayOrder = null): void
    {
        $context = Context::createDefaultContext();
        if (!$refund) {
            $refund = OrderRefundEntity::create($operationId, $amount, $hipayOrder, $status);
            $this->hipayOrderRefundRepo->create([$refund->toArray()], $context);
        } else {
            $refund->setStatus($status);
            $this->hipayOrderRefundRepo->update([$refund->toArray()], $context);
        }
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

    private function getAssociatedHiPayOrder(Criteria $criteria): ?HipayOrderEntity
    {
        return $this->hipayOrderRepo->search($criteria, Context::createDefaultContext())->first();
    }

    /**
     * Add received hipay status to the order transaction.
     */
    private function addTransactionHipayStatus(HipayOrderEntity $hipayOrder, int $hipayStatus): void
    {
        $hipayOrder->addTransactionStatus($hipayStatus);
        $this->hipayOrderRepo->update([$hipayOrder->toArray()], Context::createDefaultContext());
    }

    /**
     * Check if the transaction have all specified status from previous status.
     *
     * @param int[] $statusRequired
     */
    private function checkAllPreviousStatus(int $currentStatus, array $statusRequired, HipayOrderEntity $hipayOrder): void
    {
        $previousHipayStatus = $hipayOrder->getTransactionStatus();

        $reflectionClass = new \ReflectionClass(TransactionStatus::class);
        $constants = array_flip($reflectionClass->getConstants());

        foreach ($statusRequired as $status) {
            if (!in_array($status, $previousHipayStatus)) {
                throw new SkipNotificationException('No '.$constants[$status].' notification received for the transaction '.$hipayOrder->getTransactionId().', skip status '.$currentStatus);
            }
        }
    }

    /**
     * Check if the transaction have at least one specified status from previous status.
     *
     * @param int[] $statusRequired
     */
    private function checkOnePreviousStatus(int $currentStatus, array $statusRequired, HipayOrderEntity $hipayOrder): void
    {
        $previousHipayStatus = $hipayOrder->getTransactionStatus();

        $reflectionClass = new \ReflectionClass(TransactionStatus::class);
        $constants = array_flip($reflectionClass->getConstants());

        if (empty(array_intersect($statusRequired, $previousHipayStatus))) {
            $statusRequiredName = array_map(function ($status) use ($constants) {
                return $constants[$status];
            }, $statusRequired);
            throw new SkipNotificationException('No '.implode(' | ', $statusRequiredName).' notification received for the transaction '.$hipayOrder->getTransactionId().', skip status '.$currentStatus);
        }
    }

    /**
     * Get operation ID of transaction.
     *
     * @param array<string, mixed> $data
     */
    private function getOperationId(array $data): string
    {
        return $data['operation']['id'] ?? $data['custom_data']['operation_id'] ?? Uuid::uuid4()->toString();
    }
}
