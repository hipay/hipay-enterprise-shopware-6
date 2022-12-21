<?php

namespace HiPay\Payment\Core\Checkout\Payment\HipayOrder;

use HiPay\Payment\Core\Checkout\Payment\Capture\OrderCaptureCollection;
use HiPay\Payment\Core\Checkout\Payment\Capture\OrderCaptureEntity;
use HiPay\Payment\Core\Checkout\Payment\HipayStatusFlow\HipayStatusFlowCollection;
use HiPay\Payment\Core\Checkout\Payment\HipayStatusFlow\HipayStatusFlowEntity;
use HiPay\Payment\Core\Checkout\Payment\Refund\OrderRefundCollection;
use HiPay\Payment\Core\Checkout\Payment\Refund\OrderRefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class HipayOrderEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const HIPAY_ORDER_LOADED_EVENT = 'hipay_order.loaded';

    protected string $orderId;

    protected ?OrderEntity $order;

    protected string $transactionId;

    protected ?OrderTransactionEntity $transaction;

    protected string $transactionReference;

    protected OrderCaptureCollection $captures;

    protected OrderRefundCollection $refunds;

    protected HipayStatusFlowCollection $statusFlows;

    protected float $capturedAmount = 0;

    protected float $refundedAmount = 0;

    protected float $capturedAmountInProgress = 0;

    protected float $refundedAmountInProgress = 0;

    final public function __construct()
    {
        $this->captures = new OrderCaptureCollection();
        $this->refunds = new OrderRefundCollection();
        $this->statusFlows = new HipayStatusFlowCollection();
    }

    /**
     * Create a HiPay order object.
     */
    public static function create(string $transactionReference, OrderEntity $order, OrderTransactionEntity $transaction): self
    {
        $hipayOrder = new static();
        $hipayOrder->setTransanctionReference($transactionReference);
        $hipayOrder->setOrder($order);
        $hipayOrder->setTransaction($transaction);

        return $hipayOrder;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
        $this->setOrderId($order->getId());
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function setTransactionId(string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    public function getTransaction(): ?OrderTransactionEntity
    {
        return $this->transaction;
    }

    public function setTransaction(OrderTransactionEntity $transaction): void
    {
        $this->transaction = $transaction;
        $this->setTransactionId($transaction->getId());
    }

    public function getTransanctionReference(): string
    {
        return $this->transactionReference;
    }

    public function setTransanctionReference(string $transactionReference): void
    {
        $this->transactionReference = $transactionReference;
    }

    public function getStatusFlows(): HipayStatusFlowCollection
    {
        return $this->statusFlows;
    }

    public function setStatusFlows(HipayStatusFlowCollection $statusFlows): void
    {
        $this->statusFlows = $statusFlows;
    }

    public function addStatusFlow(HipayStatusFlowEntity $statusFlow): void
    {
        $this->statusFlows->add($statusFlow);
    }

    public function getCaptures(): OrderCaptureCollection
    {
        return $this->captures;
    }

    /**
     * Get elements from capture collection to array format.
     *
     * @return OrderCaptureEntity[]
     */
    public function getCapturesToArray(): array
    {
        return $this->getCaptures()->jsonSerialize();
    }

    public function setCaptures(OrderCaptureCollection $captures): void
    {
        $this->captures = $captures;
    }

    public function getCapturedAmount(): float
    {
        return $this->capturedAmount;
    }

    public function setCapturedAmount(float $capturedAmount): void
    {
        $this->capturedAmount = $capturedAmount;
    }

    public function getCapturedAmountInProgress(): float
    {
        return $this->capturedAmountInProgress;
    }

    public function setCapturedAmountInProgress(float $capturedAmountInProgress): void
    {
        $this->capturedAmountInProgress = $capturedAmountInProgress;
    }

    public function getRefunds(): OrderRefundCollection
    {
        return $this->refunds;
    }

    /**
     * Get elements from refund collection to array format.
     *
     * @return OrderRefundEntity[]
     */
    public function getRefundsToArray(): array
    {
        return $this->getRefunds()->jsonSerialize();
    }

    public function setRefunds(OrderRefundCollection $refunds): void
    {
        $this->refunds = $refunds;
    }

    public function getRefundedAmount(): float
    {
        return $this->refundedAmount;
    }

    public function setRefundedAmount(float $refundedAmount): void
    {
        $this->refundedAmount = $refundedAmount;
    }

    public function getRefundedAmountInProgress(): float
    {
        return $this->refundedAmountInProgress;
    }

    public function setRefundedAmountInProgress(float $refundedAmountInProgress): void
    {
        $this->refundedAmountInProgress = $refundedAmountInProgress;
    }

    /**
     * Return HiPay order to array format to use with repository.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $hipayOrder = $this->jsonSerialize();
        $hipayOrder['order'] = ['id' => $this->orderId];
        $hipayOrder['transaction'] = ['id' => $this->transactionId];
        $hipayOrder['captures'] = null;
        $hipayOrder['refunds'] = null;
        $hipayOrder['statusFlows'] = null;

        return $hipayOrder;
    }
}
