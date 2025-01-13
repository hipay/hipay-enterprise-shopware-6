<?php

namespace HiPay\Payment\Core\Checkout\Payment\Refund;

use HiPay\Payment\Enum\RefundStatus;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                   add(OrderRefundEntity $entity)
 * @method void                   set(string $key, OrderRefundEntity $entity)
 * @method OrderRefundEntity[]    getIterator()
 * @method OrderRefundEntity[]    getElements()
 * @method OrderRefundEntity|null get(string $key)
 * @method OrderRefundEntity|null first()
 * @method OrderRefundEntity|null last()
 *
 * @extends EntityCollection<OrderRefundEntity>
 */
class OrderRefundCollection extends EntityCollection
{
    public function calculRefundedAmount(): float
    {
        $refundedAmount = 0;
        foreach ($this->getElements() as $refund) {
            if ( $refund->getStatus() === RefundStatus::COMPLETED) {
                $refundedAmount += $refund->getAmount();
            }
        }

        return $refundedAmount;
    }

    public function calculRefundedAmountInProgress(): float
    {
        $refundedAmount = 0;
        foreach ($this->getElements() as $refund) {
            if (in_array(
                $refund->getStatus(),
                [RefundStatus::OPEN, RefundStatus::IN_PROGRESS, RefundStatus::COMPLETED]
            )) {
                $refundedAmount += $refund->getAmount();
            }
        }

        return $refundedAmount;
    }

    public function getRefundByOperationId(string $operationId, ?string $status = null): ?OrderRefundEntity
    {
        $result = array_filter($this->getElements(), function (OrderRefundEntity $refund) use ($operationId, $status) {
            if ($status) {
                return $refund->getOperationId() === $operationId && $refund->getStatus() === $status;
            }

            return $refund->getOperationId() === $operationId;
        });

        if (!empty($result)) {
            return reset($result);
        }

        return null;
    }

    protected function getExpectedClass(): string
    {
        return OrderRefundEntity::class;
    }
}
