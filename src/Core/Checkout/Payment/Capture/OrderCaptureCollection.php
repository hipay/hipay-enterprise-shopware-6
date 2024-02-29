<?php

namespace HiPay\Payment\Core\Checkout\Payment\Capture;

use HiPay\Payment\Enum\CaptureStatus;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                    add(OrderCaptureEntity $entity)
 * @method void                    set(string $key, OrderCaptureEntity $entity)
 * @method OrderCaptureEntity[]    getIterator()
 * @method OrderCaptureEntity[]    getElements()
 * @method OrderCaptureEntity|null get(string $key)
 * @method OrderCaptureEntity|null first()
 * @method OrderCaptureEntity|null last()
 *
 * @extends EntityCollection<OrderCaptureEntity>
 */
class OrderCaptureCollection extends EntityCollection
{
    public function calculCapturedAmount(): float
    {
        $capturedAmount = 0;
        foreach ($this->getElements() as $capture) {
            if (CaptureStatus::COMPLETED === $capture->getStatus()) {
                $capturedAmount += $capture->getAmount();
            }
        }

        return $capturedAmount;
    }

    public function calculCapturedAmountInProgress(): float
    {
        $capturedAmount = 0;
        foreach ($this->getElements() as $capture) {
            if (in_array(
                $capture->getStatus(),
                [CaptureStatus::OPEN, CaptureStatus::IN_PROGRESS, CaptureStatus::COMPLETED]
            )) {
                $capturedAmount += $capture->getAmount();
            }
        }

        return $capturedAmount;
    }

    public function getCaptureByOperationId(string $operationId, ?string $status = null): ?OrderCaptureEntity
    {
        $result = array_filter($this->getElements(), function (OrderCaptureEntity $capture) use ($operationId, $status) {
            if ($status) {
                return $capture->getOperationId() === $operationId && $capture->getStatus() === $status;
            }

            return $capture->getOperationId() === $operationId;
        });

        if (!empty($result)) {
            return reset($result);
        }

        return null;
    }

    protected function getExpectedClass(): string
    {
        return OrderCaptureEntity::class;
    }
}
