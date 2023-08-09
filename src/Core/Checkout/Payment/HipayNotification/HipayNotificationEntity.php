<?php

namespace HiPay\Payment\Core\Checkout\Payment\HipayNotification;

use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderEntity;
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

    protected ?\DateTimeInterface $notificationUpdatedAt;

    protected string $hipayOrderId;

    protected ?HipayOrderEntity $hipayOrder;

    final public function __construct()
    {
        // final constructor
    }

    /**
     * Create a notification object.
     *
     * @param array<string, mixed> $data
     */
    public static function create(int $status, array $data, \DateTimeInterface $notificationUpdatedAt, HipayOrderEntity $hipayOrder): self
    {
        $hipayNotification = new static();
        $hipayNotification->setStatus($status);
        $hipayNotification->setData($data);
        $hipayNotification->setNotificationUpdatedAt($notificationUpdatedAt);
        $hipayNotification->setHipayOrder($hipayOrder);

        return $hipayNotification;
    }

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

    public function getNotificationUpdatedAt(): \DateTimeInterface
    {
        return $this->notificationUpdatedAt;
    }

    public function setNotificationUpdatedAt(\DateTimeInterface $notificationUpdatedAt): void
    {
        $this->notificationUpdatedAt = $notificationUpdatedAt;
    }

    public function getHipayOrderId(): string
    {
        return $this->hipayOrderId;
    }

    public function setHipayOrderId(string $hipayOrderId): void
    {
        $this->hipayOrderId = $hipayOrderId;
    }

    public function getHipayOrder(): HipayOrderEntity
    {
        return $this->hipayOrder;
    }

    public function setHipayOrder(HipayOrderEntity $hipayOrder): void
    {
        $this->hipayOrder = $hipayOrder;
        $this->setHipayOrderId($hipayOrder->getId());
    }

    /**
     * Return notification to array format to use with repository.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $hipayNotification = $this->jsonSerialize();
        $hipayNotification['hipayOrder'] = ['id' => $this->hipayOrderId];

        return $hipayNotification;
    }
}
