<?php

namespace Hipay\Payment\Tests\Unit\Core\Checkout\Payment\Refund;

use HiPay\Payment\Core\Checkout\Payment\Refund\OrderRefundCollection;
use HiPay\Payment\Core\Checkout\Payment\Refund\OrderRefundEntity;
use HiPay\Payment\Enum\RefundStatus;
use PHPUnit\Framework\TestCase;

class OrderRefundCollectionTest extends TestCase
{
    public function testCalculRefundedAmount()
    {
        $refund = new OrderRefundEntity();
        $refund->setId(md5(random_int(0, PHP_INT_MAX)));
        $refund->setOperationId(md5(random_int(0, PHP_INT_MAX)));
        $refund->setAmount(5);
        $refund->setStatus(RefundStatus::IN_PROGRESS);

        $refund2 = new OrderRefundEntity();
        $refund2->setId(md5(random_int(0, PHP_INT_MAX)));
        $refund2->setOperationId(md5(random_int(0, PHP_INT_MAX)));
        $refund2->setAmount(2);
        $refund2->setStatus(RefundStatus::COMPLETED);

        $refund3 = new OrderRefundEntity();
        $refund3->setId(md5(random_int(0, PHP_INT_MAX)));
        $refund3->setOperationId(md5(random_int(0, PHP_INT_MAX)));
        $refund3->setAmount(6.05);
        $refund3->setStatus(RefundStatus::COMPLETED);

        $orderRefundCollection = new OrderRefundCollection([$refund, $refund2, $refund3]);

        $refundedAmount = $orderRefundCollection->calculRefundedAmount();

        $this->assertEquals(8.05, $refundedAmount);
    }

    public function testCalculRefundedAmountInProgress()
    {
        $refund = new OrderRefundEntity();
        $refund->setId(md5(random_int(0, PHP_INT_MAX)));
        $refund->setOperationId(md5(random_int(0, PHP_INT_MAX)));
        $refund->setAmount(5);
        $refund->setStatus(RefundStatus::IN_PROGRESS);

        $refund2 = new OrderRefundEntity();
        $refund2->setId(md5(random_int(0, PHP_INT_MAX)));
        $refund2->setOperationId(md5(random_int(0, PHP_INT_MAX)));
        $refund2->setAmount(2);
        $refund2->setStatus(RefundStatus::COMPLETED);

        $refund3 = new OrderRefundEntity();
        $refund3->setId(md5(random_int(0, PHP_INT_MAX)));
        $refund3->setOperationId(md5(random_int(0, PHP_INT_MAX)));
        $refund3->setAmount(6.05);
        $refund3->setStatus(RefundStatus::CANCEL);

        $refund4 = new OrderRefundEntity();
        $refund4->setId(md5(random_int(0, PHP_INT_MAX)));
        $refund4->setOperationId(md5(random_int(0, PHP_INT_MAX)));
        $refund4->setAmount(1.04);
        $refund4->setStatus(RefundStatus::OPEN);

        $orderRefundCollection = new OrderRefundCollection([$refund, $refund2, $refund3, $refund4]);

        $refundedAmount = $orderRefundCollection->calculRefundedAmountInProgress();

        $this->assertEquals(8.04, $refundedAmount);
    }
}
