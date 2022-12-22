<?php

namespace HiPay\Payment\Core\Checkout\Payment\Refund;

use HiPay\Fullservice\Exception\UnexpectedValueException;
use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderEntity;
use HiPay\Payment\Enum\RefundStatus;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OrderRefundEntity extends Entity
{
    use EntityIdTrait;

    protected string $hipayOrderId;

    protected ?HipayOrderEntity $hipayOrder;

    protected string $operationId;

    protected float $amount;

    protected string $status;

    final public function __construct()
    {
        // final constructor
    }

    public static function create(string $operationId, float $amount, HipayOrderEntity $hipayOrder, string $status = RefundStatus::OPEN): self
    {
        $refund = new static();
        $refund->setOperationId($operationId);
        $refund->setAmount($amount);
        $refund->setHipayOrder($hipayOrder);
        $refund->setStatus($status);

        return $refund;
    }

    public function getHipayOrderId(): string
    {
        return $this->hipayOrderId;
    }

    public function setHipayOrderId(string $hipayOrderId): void
    {
        $this->hipayOrderId = $hipayOrderId;
    }

    public function getHipayOrder(): ?HipayOrderEntity
    {
        return $this->hipayOrder;
    }

    public function setHipayOrder(HipayOrderEntity $hipayOrder): void
    {
        $this->hipayOrder = $hipayOrder;
        $this->setHipayOrderId($hipayOrder->getId());
    }

    public function getOperationId(): string
    {
        return $this->operationId;
    }

    public function setOperationId(string $operationId): void
    {
        $this->operationId = $operationId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $reflectionClass = new \ReflectionClass(RefundStatus::class);
        $statusList = array_flip($reflectionClass->getConstants());
        if (!in_array($status, $statusList)) {
            throw new UnexpectedValueException('Status '.$status.' not permitted');
        }
        $this->status = $status;
    }

    /**
     * Return refund to array format to use with repository.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $refund = $this->jsonSerialize();
        $refund['hipayOrder'] = ['id' => $this->hipayOrderId];

        return $refund;
    }
}
