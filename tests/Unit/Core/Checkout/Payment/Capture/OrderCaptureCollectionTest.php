<?php

namespace HiPay\Payment\Tests\Unit\Core\Checkout\Payment\Capture;

use HiPay\Payment\Core\Checkout\Payment\Capture\OrderCaptureCollection;
use HiPay\Payment\Core\Checkout\Payment\Capture\OrderCaptureEntity;
use HiPay\Payment\Enum\CaptureStatus;
use PHPUnit\Framework\TestCase;

class OrderCaptureCollectionTest extends TestCase
{
    public function testCalculCapturedAmount()
    {
        $capture = new OrderCaptureEntity();
        $capture->setId(md5(random_int(0, PHP_INT_MAX)));
        $capture->setOperationId(md5(random_int(0, PHP_INT_MAX)));
        $capture->setAmount(5);
        $capture->setStatus(CaptureStatus::IN_PROGRESS);

        $capture2 = new OrderCaptureEntity();
        $capture2->setId(md5(random_int(0, PHP_INT_MAX)));
        $capture2->setOperationId(md5(random_int(0, PHP_INT_MAX)));
        $capture2->setAmount(2);
        $capture2->setStatus(CaptureStatus::COMPLETED);

        $capture3 = new OrderCaptureEntity();
        $capture3->setId(md5(random_int(0, PHP_INT_MAX)));
        $capture3->setOperationId(md5(random_int(0, PHP_INT_MAX)));
        $capture3->setAmount(6.05);
        $capture3->setStatus(CaptureStatus::COMPLETED);

        $orderCaptureCollection = new OrderCaptureCollection([$capture, $capture2, $capture3]);

        $capturedAmount = $orderCaptureCollection->calculCapturedAmount();

        $this->assertEquals(8.05, $capturedAmount);
    }

    public function testCalculCapturedAmountInProgress()
    {
        $capture = new OrderCaptureEntity();
        $capture->setId(md5(random_int(0, PHP_INT_MAX)));
        $capture->setOperationId(md5(random_int(0, PHP_INT_MAX)));
        $capture->setAmount(5);
        $capture->setStatus(CaptureStatus::IN_PROGRESS);

        $capture2 = new OrderCaptureEntity();
        $capture2->setId(md5(random_int(0, PHP_INT_MAX)));
        $capture2->setOperationId(md5(random_int(0, PHP_INT_MAX)));
        $capture2->setAmount(2);
        $capture2->setStatus(CaptureStatus::COMPLETED);

        $capture3 = new OrderCaptureEntity();
        $capture3->setId(md5(random_int(0, PHP_INT_MAX)));
        $capture3->setOperationId(md5(random_int(0, PHP_INT_MAX)));
        $capture3->setAmount(6.05);
        $capture3->setStatus(CaptureStatus::CANCEL);

        $capture4 = new OrderCaptureEntity();
        $capture4->setId(md5(random_int(0, PHP_INT_MAX)));
        $capture4->setOperationId(md5(random_int(0, PHP_INT_MAX)));
        $capture4->setAmount(1.04);
        $capture4->setStatus(CaptureStatus::OPEN);

        $orderCaptureCollection = new OrderCaptureCollection([$capture, $capture2, $capture3, $capture4]);

        $capturedAmount = $orderCaptureCollection->calculCapturedAmountInProgress();

        $this->assertEquals(8.04, $capturedAmount);
    }
}
