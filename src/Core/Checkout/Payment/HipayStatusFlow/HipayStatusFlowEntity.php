<?php

namespace HiPay\Payment\Core\Checkout\Payment\HipayStatusFlow;

use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class HipayStatusFlowEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const HIPAY_ORDER_LOADED_EVENT = 'hipay_status_flow.loaded';

    protected string $hipayOrderId;

    protected ?HipayOrderEntity $hipayOrder;

    protected int $code;

    protected ?string $name;

    protected string $message;

    protected float $amount;

    protected string $hash;

    /**
     * Create a HiPay statusFlow object.
     */
    public static function create(HipayOrderEntity $hipayOrder, int $code, string $message, float $amount, string $hash): self
    {
        $statusFlow = new static();
        $statusFlow->setCode($code);
        $statusFlow->setHipayOrder($hipayOrder);
        $statusFlow->setCode($code);
        $statusFlow->setMessage($message);
        $statusFlow->setAmount($amount);
        $statusFlow->setHash($hash);

        return $statusFlow;
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

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCode(int $code): void
    {
        $this->code = $code;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    /**
     * Return HiPay order to array format to use with repository.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $statusFlow = $this->jsonSerialize();
        $statusFlow['hipayOrder'] = ['id' => $this->hipayOrderId];

        return $statusFlow;
    }
}
