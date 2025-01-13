<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\ApplePay;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class ApplePayTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [
            'token' => static::class,
            'payment_product' => 'foo,bar',
        ];

        $response2 = [
            'token' => static::class,
            'payment_product' => 'foo',
        ];

        $orderRequest = $this->getHostedFiledsOrderRequest(ApplePay::class, $response);
        $orderRequest2 = $this->getHostedFiledsOrderRequest(ApplePay::class, $response2);

        $this->assertSame(
            $response['token'],
            $orderRequest->paymentMethod->cardtoken
        );
        $this->assertSame(
            $response2['token'],
            $orderRequest2->paymentMethod->cardtoken
        );

        $this->assertSame(
            7,
            $orderRequest->paymentMethod->eci
        );
        $this->assertSame(
            7,
            $orderRequest2->paymentMethod->eci
        );

        $this->assertSame(
            $response['payment_product'],
            $orderRequest->payment_product
        );
        $this->assertSame(
            $response2['payment_product'],
            $orderRequest2->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(
            ApplePay::class,
            null,
            ['transaction.payment_method.custom_fields' => ['isApplePay' => '1']],
            [$this->createMock(EntityRepository::class)],
        );

        $this->assertSame(
            ApplePay::getProductCode(),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            5,
            ApplePay::getPosition()
        );

        $this->assertEquals(
            'hipay-applepay',
            ApplePay::getTechnicalName()
        );

        $this->assertEquals(
            [
                'haveHostedFields' => true,
                'allowPartialCapture' => true,
                'allowPartialRefund' => true,
            ],
            ApplePay::getConfig()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order with Apple Pay',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung mit der Apple Pay',
                'fo-FO' => null,
            ],
            [
                'en-GB' => ApplePay::getDescription('en-GB'),
                'de-DE' => ApplePay::getDescription('de-DE'),
                'fo-FO' => ApplePay::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Apple Pay',
                'de-DE' => 'Apple Pay',
                'fo-FO' => null,
            ],
            [
                'en-GB' => ApplePay::getName('en-GB'),
                'de-DE' => ApplePay::getName('de-DE'),
                'fo-FO' => ApplePay::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'applepay.svg',
            ApplePay::getImage()
        );

        $this->assertSame(
            null,
            ApplePay::getCountries()
        );

        $this->assertSame(
            null,
            ApplePay::getCurrencies()
        );
    }
}
