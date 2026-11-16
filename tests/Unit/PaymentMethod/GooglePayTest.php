<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\GooglePay;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;

class GooglePayTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [
            'token' => static::class,
            'payment_product' => 'visa',
        ];

        $orderRequest = $this->getHostedFiledsOrderRequest(GooglePay::class, $response);

        $this->assertSame($response['token'], $orderRequest->paymentMethod->cardtoken);
        $this->assertSame(7, $orderRequest->paymentMethod->eci);
        $this->assertSame(1, $orderRequest->paymentMethod->authentication_indicator);
        $this->assertSame('visa', $orderRequest->payment_product);
        $this->assertArrayNotHasKey('isApplePay', $orderRequest->custom_data ?? []);
    }

    public function testStaticValues()
    {
        $this->assertSame('hipay-googlepay', GooglePay::getTechnicalName());
        $this->assertSame('googlepay.svg', GooglePay::getImage());
        $this->assertSame(6, GooglePay::getPosition());
        $this->assertSame('Google Pay', GooglePay::getName('en-GB'));
        $this->assertSame('Pay your order with Google Pay', GooglePay::getDescription('en-GB'));
        $this->assertNull(GooglePay::getName('fo-FO'));
        $this->assertNull(GooglePay::getDescription('fo-FO'));
        $this->assertSame('cb,visa,mastercard,maestro', GooglePay::getProductCode());
        $this->assertTrue(GooglePay::getConfig()['forceHostedFields']);
    }
}
