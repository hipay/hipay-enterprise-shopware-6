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
use HiPay\Payment\Core\Checkout\Payment\HipayStatusFlow\HipayStatusFlowEntity;
use HiPay\Payment\Core\Checkout\Payment\Refund\OrderRefundEntity;
use HiPay\Payment\Enum\CaptureStatus;
use HiPay\Payment\Enum\RefundStatus;
use HiPay\Payment\Exception\ExpiredNotificationException;
use HiPay\Payment\Exception\SkipNotificationException;
use HiPay\Payment\Logger\HipayLogger;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
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

    private EntityRepository $tokenRepo;

    private OrderTransactionStateHandler $orderTransactionStateHandler;

    private HipayLogger $logger;

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
        EntityRepository $hipayCardTokenRepository,
        ReadHipayConfigService $config,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        HipayLogger $hipayLogger
    ) {
        $this->transactionRepo = $transactionRepository;
        $this->notificationRepo = $hipayNotificationRepository;
        $this->hipayOrderRepo = $hipayOrderRepository;
        $this->hipayOrderCaptureRepo = $hipayOrderCaptureRepository;
        $this->hipayOrderRefundRepo = $hipayOrderRefundRepository;
        $this->tokenRepo = $hipayCardTokenRepository;
        $this->config = $config;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->logger = $hipayLogger->setChannel(HipayLogger::NOTIFICATION);
    }

    /**
     * Save the received notification request.
     */
    public function saveNotificationRequest(Request $request): void
    {
        $context = Context::createDefaultContext();
        $parameters = $request->request->all();

        if (!$this->validateRequest($request, $parameters)) {
            throw new AccessDeniedException('Signature does not match');
        }

        if (!$request->get('date_updated') || !$notificationDate = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sO', $request->get('date_updated'))) {
            throw new MissingMandatoryParametersException('date_updated is mandatory');
        }

        if (!$orderTransactionId = ($parameters['custom_data']['transaction_id'] ?? null)) {
            throw new MissingMandatoryParametersException('custom_data.transaction_id is mandatory');
        }

        if (!$transactionReference = $request->request->getAlnum('transaction_reference')) {
            throw new MissingMandatoryParametersException('transaction_reference is mandatory');
        }

        $transactionCriteria = (new Criteria([$orderTransactionId]))->addAssociation('order');
        if (!$transaction = $this->transactionRepo->search($transactionCriteria, $context)->first()) {
            throw new NotFoundResourceException('Transaction '.$orderTransactionId.' is not found');
        }
        /** @var OrderTransactionEntity $transaction */

        // Create or update if exists a HiPay order related to this transaction to database
        $orderCriteria = (new Criteria())->addFilter(new EqualsFilter('orderId', $transaction->getOrderId()));

        /** @var ?HipayOrderEntity $hipayOrder */
        $hipayOrder = $this->getAssociatedHiPayOrder($orderCriteria);
        if (!$hipayOrder) {
            $hipayOrder = HipayOrderEntity::create($transactionReference, $transaction->getOrder(), $transaction);
            $this->hipayOrderRepo->create([$hipayOrder->toArray()], $context);
            /** @var HipayOrderEntity $hipayOrder after Creation */
            $hipayOrder = $this->getAssociatedHiPayOrder($orderCriteria);
        } else {
            $hipayOrder->setTransactionReference($transactionReference);
            $hipayOrder->setOrder($transaction->getOrder());
            $hipayOrder->setTransaction($transaction);
            $this->hipayOrderRepo->update([$hipayOrder->toArray()], $context);
        }

        // Create notification to database
        $notification = HipayNotificationEntity::create(
            $this->getStatus($request->request->getInt('status'), $request->request),
            $parameters,
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
    private function validateRequest(Request $request, array $parameters): bool
    {
        $isApplePay = false;
        if (isset($parameters['custom_data']['isApplePay'])) {
            $isApplePay = true;
        }

        $algos = [
            'sha256' => HashAlgorithm::SHA256,
            'sha512' => HashAlgorithm::SHA512,
        ];

        if ($isApplePay) {
            if (!isset($algos[$this->config->getHashApplePay()])) {
                throw new ApiErrorException('Bad configuration unknown algorythm "'.$this->config->getHashApplePay().'"');
            }
        } else {
            if (!isset($algos[$this->config->getHash()])) {
                throw new ApiErrorException('Bad configuration unknown algorythm "'.$this->config->getHash().'"');
            }
        }

        if (!$signature = $request->headers->get('x-allopass-signature', null)) {
            throw new UnauthorizedHttpException('header', 'Missing signature header');
        }

        return Signature::isValidHttpSignature(
            $isApplePay ? $this->config->getApplePayPassphrase() : $this->config->getPassphrase(),
            $algos[$isApplePay ? $this->config->getHashApplePay() : $this->config->getHash()],
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
            case TransactionStatus::CAPTURE_REFUSED:
                return static::PROCESS_AFTER_AUTHORIZE;
                // Refund requested
            case TransactionStatus::REFUND_REQUESTED:
            case TransactionStatus::REFUND_REFUSED:
                return static::PROCESS_AFTER_CAPTURE;
                // Paid partially
            case TransactionStatus::PARTIALLY_CAPTURED:
                return static::PAY_PARTIALLY;
                // Paid
            case TransactionStatus::CAPTURED:
                if (floatval($request->get('captured_amount')) < floatval($request->get('authorized_amount'))) {
                    return static::PAY_PARTIALLY;
                }

                return static::PAID;
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
                }

                return static::REFUNDED;
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
                    $this->logger->error('Error during an Hipay notification '.$notificationId.' dispatching : '.$e->getMessage());
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

        /** @var HipayOrderEntity */
        $hipayOrder = $this->getAssociatedHiPayOrder(
            (new Criteria([$notification->getHipayOrderId()]))
                ->addAssociations(['transaction', 'captures', 'refunds', 'statusFlows', 'order.orderCustomer'])
        );

        $this->logger->debug('Dispatching notification '.$notification->getId().' for the transaction '.$hipayOrder->getTransactionId());

        $data = $notification->getData();

        // Validation
        $hash = dechex(crc32((string) json_encode($data)));
        if (count($hipayOrder->getStatusFlows()->filter(fn ($f) => $f->getHash() === $hash))) {
            $this->logger->info('Notification '.$notification->getId().' skipped, status already treated');

            return;
        }

        if (!isset(static::CONVERT_STATE[$notification->getStatus()])) {
            throw new UnexpectedValueException('Bad status code for Hipay notification '.$notification->getId());
        }

        $context = Context::createDefaultContext();
        $hipayStatus = intval($data['status']);
        $stateMachine = $hipayOrder->getTransaction()->getStateMachineState()->getTechnicalName();
        $statusChange = false;

        $amount = $data['operation']['amount'] ?? $data['authorized_amount'];

        switch ($notification->getStatus()) {
            case static::PROCESS:
                $this->orderTransactionStateHandler->process($hipayOrder->getTransactionId(), $context);
                $statusChange = true;
                break;

            case static::FAILED:
                $this->orderTransactionStateHandler->fail($hipayOrder->getTransactionId(), $context);
                $statusChange = true;
                break;

            case static::CHARGEDBACK:
                $this->orderTransactionStateHandler->chargeback($hipayOrder->getTransactionId(), $context);
                $statusChange = true;
                break;

            case static::AUTHORIZE:
                if ($data['custom_data']['multiuse'] ?? false) {
                    $this->savePaymentToken(
                        ['brand' => $data['payment_product']] + $data['payment_method'],
                        $hipayOrder->getOrder()->getOrderCustomer()->getCustomerId()
                    );
                }

                $this->orderTransactionStateHandler->authorize($hipayOrder->getTransactionId(), $context);
                $this->handleSepaAuthorizedNotification($notification, $hipayOrder);
                $statusChange = true;
                break;

            case static::PROCESS_AFTER_AUTHORIZE:
                $amount = $data['operation']['amount'] ?? $data['captured_amount'];
                $this->handleProcessAfterAuthorizeNotification($notification, $hipayOrder);
                break;

            case static::PAY_PARTIALLY:
            case static::PAID:
                $amount = $data['operation']['amount'] ?? $data['captured_amount'];
                $statusChange = $this->handleAuthorizedNotification($notification, $hipayOrder);
                break;

            case static::PROCESS_AFTER_CAPTURE:
                $amount = $data['operation']['amount'] ?? $data['refunded_amount'];
                $this->handleProcessAfterCaptureNotification($notification, $hipayOrder);
                break;

            case static::REFUNDED_PARTIALLY:
            case static::REFUNDED:
                $amount = $data['operation']['amount'] ?? $data['refunded_amount'];
                $statusChange = $this->handleCapturedNotification($notification, $hipayOrder);
                break;

            case static::CANCELLED:
                $this->orderTransactionStateHandler->cancel($hipayOrder->getTransactionId(), $context);
                $statusChange = true;
                break;
        }

        $this->addHipayStatusFlow($hipayOrder, $hipayStatus, $data['reason']['message'] ?? '', $amount, $hash);

        if ($statusChange) {
            $this->logger->info(
                'Change order transaction '.$hipayOrder->getTransactionId()
                .' to status '.static::CONVERT_STATE[$notification->getStatus()].' (previously '.$stateMachine.')'
            );
        }
    }

    /**
     * Handle notifications that create or fail capture on an AUTHORIZED order.
     */
    private function handleProcessAfterAuthorizeNotification(HipayNotificationEntity $notification, HipayOrderEntity $hipayOrder): void
    {
        $data = $notification->getData();

        $hipayStatus = intval($data['status']);
        $operationId = $this->getOperationId($data);

        $capture = $hipayOrder->getCaptures()->getCaptureByOperationId($operationId);

        if (TransactionStatus::CAPTURE_REQUESTED === $hipayStatus) {
            $this->checkAllPreviousStatus($hipayStatus, [TransactionStatus::AUTHORIZED], $hipayOrder);

            if ($capture && CaptureStatus::IN_PROGRESS === $capture->getStatus()) {
                $this->logger->info('Ignore notification '.$notification->getId().'. Capture '.$capture->getOperationId().' already in progress');
            } else {
                if (!$capture) {
                    $this->logger->info('Notification '.$notification->getId().' create IN_PROGRESS capture for the transaction '.$hipayOrder->getTransactionId());
                } else {
                    $this->logger->info('Notification '.$notification->getId().' update capture '.$capture->getOperationId().' to IN_PROGRESS status for the transaction '.$hipayOrder->getTransactionId());
                }

                $capturedAmount = $data['operation']['amount'] ?? $data['captured_amount'];
                $this->saveCapture(CaptureStatus::IN_PROGRESS, $capture, $capturedAmount, $operationId, $hipayOrder);
            }

            return;
        }

        if (TransactionStatus::CAPTURE_REFUSED === $hipayStatus) {
            $this->checkAllPreviousStatus($hipayStatus, [TransactionStatus::AUTHORIZED, TransactionStatus::CAPTURE_REQUESTED], $hipayOrder);

            if (!$capture || CaptureStatus::IN_PROGRESS !== $capture->getStatus()) {
                throw new SkipNotificationException('No IN_PROGRESS capture found with operation ID '.$operationId.' for the transaction '.$hipayOrder->getTransactionId());
            }

            $this->saveCapture(CaptureStatus::FAILED, $capture);
        }
    }

    /**
     * Handle notifications that create or fail capture on an AUTHORIZED order For SEPA SDD.
     */
    private function handleSepaAuthorizedNotification(HipayNotificationEntity $notification, HipayOrderEntity $hipayOrder): bool
    {
        $data = $notification->getData();
        if (!empty($data['payment_product']) && 'sdd' === $data['payment_product']) {
            $hipayStatus = intval($data['status']);
            $operationId = $this->getOperationId($data);

            $capture = $hipayOrder->getCaptures()->getCaptureByOperationId($operationId);
            if (TransactionStatus::AUTHORIZED === $hipayStatus) {
                $this->checkAllPreviousStatus($hipayStatus, [TransactionStatus::AUTHORIZATION_REQUESTED], $hipayOrder);

                if ($capture && CaptureStatus::IN_PROGRESS === $capture->getStatus()) {
                    $this->logger->info('Ignore notification '.$notification->getId().'. Capture '.$capture->getOperationId().' already in progress');
                } else {
                    if (!$capture) {
                        $this->logger->info('Notification '.$notification->getId().' create IN_PROGRESS capture for the transaction '.$hipayOrder->getTransactionId());
                    } else {
                        $this->logger->info('Notification '.$notification->getId().' update capture '.$capture->getOperationId().' to IN_PROGRESS status for the transaction '.$hipayOrder->getTransactionId());
                    }

                    $capturedAmount = $data['operation']['amount'] ?? $data['captured_amount'];
                    $this->saveCapture(CaptureStatus::IN_PROGRESS, $capture, $capturedAmount, $operationId, $hipayOrder);
                }
            }
        }

        return true;
    }

    /**
     * Handle notification who need AUTHORIZED notification.
     */
    private function handleAuthorizedNotification(HipayNotificationEntity $notification, HipayOrderEntity $hipayOrder): bool
    {
        $context = Context::createDefaultContext();
        $data = $notification->getData();

        $hipayStatus = intval($data['status']);
        $operationId = $this->getOperationId($data);

        $this->checkAllPreviousStatus($hipayStatus, [TransactionStatus::AUTHORIZED], $hipayOrder);

        if (!$capture = $hipayOrder->getCaptures()->getCaptureByOperationId($operationId)) {
            throw new SkipNotificationException('No capture found with operation ID '.$operationId.' for the transaction '.$hipayOrder->getTransactionId());
        }

        $this->saveCapture(CaptureStatus::COMPLETED, $capture);

        switch ($notification->getStatus()) {
            case static::PAY_PARTIALLY:
                if ($hipayOrder->getTransaction()->getStateMachineState()->getTechnicalName() === static::CONVERT_STATE[static::REFUNDED_PARTIALLY]) {
                    // Transition to OPEN before PAY_PARTIALLY because Shopware cannot change status from REFUNDED_PARTIALLY
                    $this->orderTransactionStateHandler->reopen($hipayOrder->getTransactionId(), $context);
                }
                $this->orderTransactionStateHandler->payPartially($hipayOrder->getTransactionId(), $context);
                break;

            case static::PAID:
                if ($hipayOrder->getTransaction()->getStateMachineState()->getTechnicalName() === static::CONVERT_STATE[static::REFUNDED_PARTIALLY]) {
                    // Transition to OPEN before PAID because Shopware cannot change status from REFUNDED_PARTIALLY
                    $this->orderTransactionStateHandler->reopen($hipayOrder->getTransactionId(), $context);
                }
                $this->orderTransactionStateHandler->paid($hipayOrder->getTransactionId(), $context);
                break;
        }

        return true;
    }

    /**
     * Handle notification that create or fail refund on a CAPTURED order.
     */
    private function handleProcessAfterCaptureNotification(HipayNotificationEntity $notification, HipayOrderEntity $hipayOrder): void
    {
        $data = $notification->getData();

        $hipayStatus = intval($data['status']);
        $operationId = $this->getOperationId($data);

        $refund = $hipayOrder->getRefunds()->getRefundByOperationId($operationId);

        if (TransactionStatus::REFUND_REQUESTED === $hipayStatus) {
            $refundedAmount = $data['operation']['amount'] ?? $data['refunded_amount'];

            if ($refund && RefundStatus::IN_PROGRESS === $refund->getStatus()) {
                $this->logger->info('Ignore notification '.$notification->getId().'. Refund '.$refund->getId().' already in progress');
            } else {
                if (!$refund) {
                    $this->logger->info('Notification '.$notification->getId().' create IN_PROGRESS refund for the transaction '.$hipayOrder->getTransactionId());
                } else {
                    $this->logger->info('Notification '.$notification->getId().' update refund '.$refund->getId().' to IN_PROGRESS status for the transaction '.$hipayOrder->getTransactionId());
                }
                $this->saveRefund(RefundStatus::IN_PROGRESS, $refund, $refundedAmount, $operationId, $hipayOrder);
            }

            return;
        }

        if (TransactionStatus::REFUND_REFUSED === $hipayStatus) {
            $this->checkAllPreviousStatus($hipayStatus, [TransactionStatus::CAPTURED, TransactionStatus::REFUND_REQUESTED], $hipayOrder);

            if (!$refund || RefundStatus::IN_PROGRESS !== $refund->getStatus()) {
                throw new SkipNotificationException('No IN_PROGRESS refund found with operation ID '.$operationId.' for the transaction '.$hipayOrder->getTransactionId());
            }

            $this->saveRefund(RefundStatus::FAILED, $refund);
        }
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

        $this->checkOnePreviousStatus($hipayStatus, [TransactionStatus::CAPTURED, TransactionStatus::PARTIALLY_CAPTURED], $hipayOrder);

        if (!$refund = $hipayOrder->getRefunds()->getRefundByOperationId($operationId)) {
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

    private function getAssociatedHiPayOrder(Criteria $criteria): ?Entity
    {
        return $this->hipayOrderRepo->search($criteria, Context::createDefaultContext())->first();
    }

    /**
     * Add received hipay status to the order transaction.
     */
    private function addHipayStatusFlow(HipayOrderEntity $order, int $code, string $message, float $amount, string $hash): void
    {
        $this->hipayOrderRepo->update(
            [
                [
                    'id' => $order->getId(),
                    'statusFlows' => [HipayStatusFlowEntity::create($order, $code, $message, $amount, $hash)->toArray()],
                ],
            ],
            Context::createDefaultContext()
        );
    }

    /**
     * Check if the transaction have all specified status from previous status.
     *
     * @param int[] $statusRequired
     */
    private function checkAllPreviousStatus(int $currentStatus, array $statusRequired, HipayOrderEntity $hipayOrder): void
    {
        $previousHipayStatus = $hipayOrder->getStatusFlows()->map(fn (HipayStatusFlowEntity $s) => $s->getCode());

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
        $previousHipayStatus = $hipayOrder->getStatusFlows()->map(fn (HipayStatusFlowEntity $s) => $s->getCode());

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

    /**
     * Save payment token.
     *
     * @param array<mixed,string> $paymentMethod
     */
    private function savePaymentToken(array $paymentMethod, string $customerId): void
    {
        $this->tokenRepo->upsert([[
            'token' => $paymentMethod['token'],
            'brand' => $paymentMethod['brand'],
            'pan' => $paymentMethod['pan'],
            'cardHolder' => $paymentMethod['card_holder'],
            'cardExpiryMonth' => $paymentMethod['card_expiry_month'],
            'cardExpiryYear' => $paymentMethod['card_expiry_year'],
            'issuer' => $paymentMethod['issuer'],
            'country' => $paymentMethod['country'],
            'customerId' => $customerId,
        ]], Context::createDefaultContext());
    }
}
