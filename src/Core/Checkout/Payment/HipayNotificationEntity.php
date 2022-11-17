<?php

namespace HiPay\Payment\Core\Checkout\Payment;

use DateTimeInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class HipayNotificationEntity extends Entity
{
    use EntityIdTrait;

    protected ?int $status;

    /**
     * @var array<string,mixed>|null
     */
    protected ?array $data;

    protected ?DateTimeInterface  $notificationUpdatedAt;

    protected ?string $orderTransactionId;

    protected ?OrderTransactionEntity  $orderTransaction;

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @param array<string,mixed>|null $data
     */
    public function setData(?array $data): void
    {
        $this->data = $data;
    }

    public function getNotificationUpdatedAt(): DateTimeInterface
    {
        return $this->notificationUpdatedAt;
    }

    public function setNotificationUpdatedAt(DateTimeInterface $notificationUpdatedAt): void
    {
        $this->notificationUpdatedAt = $notificationUpdatedAt;
    }

    public function getOrderTransactionId(): string
    {
        return $this->orderTransactionId;
    }

    public function setOrderTransactionId(string $orderTransactionId): void
    {
        $this->orderTransactionId = $orderTransactionId;
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        return $this->orderTransaction;
    }

    public function setOrderTransaction(OrderTransactionEntity $orderTransaction): void
    {
        $this->orderTransaction = $orderTransaction;
    }
}
