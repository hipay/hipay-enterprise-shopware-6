<?php

namespace Hipay\Payment\Tests\Unit\Core\Checkout\Payment\HipayOrder;

use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderEntity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class HipayOrderEntityTest extends TestCase
{
    public function testToArray()
    {
        $order = new OrderEntity();
        $order->setId('ORDER_ID');

        $transaction = new OrderTransactionEntity();
        $transaction->setId('TRX_ID');

        $hipayOrder = new HipayOrderEntity();
        $hipayOrder->setId('HIPAY_ID');
        $hipayOrder->setOrder($order);
        $hipayOrder->setTransaction($transaction);
        $hipayOrder->setTransanctionReference('TRX_REF');
        $hipayOrder->setTransactionStatus([1, 2, 3]);

        $formattedHipayOrder = $hipayOrder->toArray();

        $this->assertEquals($hipayOrder->getId(), $formattedHipayOrder['id']);
        $this->assertEquals($hipayOrder->getTransanctionReference(), $formattedHipayOrder['transactionReference']);
        $this->assertEquals($hipayOrder->getTransactionStatus(), $formattedHipayOrder['transactionStatus']);
        $this->assertEquals($hipayOrder->getOrderId(), $formattedHipayOrder['orderId']);
        $this->assertEquals(['id' => $hipayOrder->getOrderId()], $formattedHipayOrder['order']);
        $this->assertEquals($hipayOrder->getTransactionId(), $formattedHipayOrder['transactionId']);
        $this->assertEquals(['id' => $hipayOrder->getTransactionId()], $formattedHipayOrder['transaction']);
        $this->assertEquals(null, $formattedHipayOrder['captures']);
        $this->assertEquals(null, $formattedHipayOrder['refunds']);
    }
}
