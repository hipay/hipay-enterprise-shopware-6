<?php

namespace HiPay\Payment\Tests\Unit\Core\Checkout\Payment\HipayOrder;

use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderEntity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class HipayOrderEntityTest extends TestCase
{
    public function testCreate()
    {
        $order = new OrderEntity();
        $order->setId('ORDER_ID');
        $order->setVersionId('ORDER_VERSION_ID');

        $transaction = new OrderTransactionEntity();
        $transaction->setId('TRX_ID');
        $transaction->setVersionId('TRX_VERSION_ID');

        $hipayOrder = HipayOrderEntity::create('REF', $order, $transaction, [116]);

        $this->assertEquals('REF', $hipayOrder->getTransanctionReference());
        $this->assertEquals('ORDER_ID', $hipayOrder->getOrderId());
        $this->assertEquals('TRX_ID', $hipayOrder->getTransactionId());
    }

    public function testToArray()
    {
        $order = new OrderEntity();
        $order->setId('ORDER_ID');
        $order->setVersionId('ORDER_VERSION_ID');

        $transaction = new OrderTransactionEntity();
        $transaction->setId('TRX_ID');
        $transaction->setVersionId('TRX_VERSION_ID');

        $hipayOrder = new HipayOrderEntity();
        $hipayOrder->setId('HIPAY_ID');
        $hipayOrder->setOrder($order);
        $hipayOrder->setTransaction($transaction);
        $hipayOrder->setTransanctionReference('TRX_REF');

        $formattedHipayOrder = $hipayOrder->toArray();

        $this->assertEquals($hipayOrder->getId(), $formattedHipayOrder['id']);
        $this->assertEquals($hipayOrder->getTransanctionReference(), $formattedHipayOrder['transactionReference']);
        $this->assertEquals($hipayOrder->getOrderId(), $formattedHipayOrder['orderId']);
        $this->assertEquals($hipayOrder->getOrderVersionId(), $formattedHipayOrder['orderVersionId']);
        $this->assertEquals(['id' => $hipayOrder->getOrderId(), 'versionId' => $hipayOrder->getOrderVersionId()], $formattedHipayOrder['order']);
        $this->assertEquals($hipayOrder->getTransactionId(), $formattedHipayOrder['transactionId']);
        $this->assertEquals($hipayOrder->getTransactionVersionId(), $formattedHipayOrder['transactionVersionId']);
        $this->assertEquals(['id' => $hipayOrder->getTransactionId(), 'versionId' => $hipayOrder->getTransactionVersionId()], $formattedHipayOrder['transaction']);
        $this->assertEquals(null, $formattedHipayOrder['captures']);
        $this->assertEquals(null, $formattedHipayOrder['refunds']);
        $this->assertEquals(null, $formattedHipayOrder['statusFlows']);
    }
}
