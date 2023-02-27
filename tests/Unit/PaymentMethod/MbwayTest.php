<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Mbway;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;

class MbwayTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [
            'phone' => '+351289094089',
        ];

        $orderRequest = $this->getHostedFiledsOrderRequest(Mbway::class, $response);

        $this->assertSame(
            Mbway::getProductCode(),
            $orderRequest->payment_product
        );

        $this->assertSame(
            '289094089',
            $orderRequest->paymentMethod->phone
        );

        $this->assertSame(
            '289094089',
            $orderRequest->customerBillingInfo->phone
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Mbway::class);

        $this->assertSame(
            Mbway::getProductCode(),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            [],
            Mbway::addDefaultCustomFields()
        );

        $this->assertEquals(
            ['haveHostedFields' => true, 'allowPartialCapture' => true, 'allowPartialRefund' => true],
            Mbway::getConfig()
        );

        $this->assertEquals(
            70,
            Mbway::getPosition()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order with the MB Way application',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung mit der MB Way Anwendung',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Mbway::getDescription('en-GB'),
                'de-DE' => Mbway::getDescription('de-DE'),
                'fo-FO' => Mbway::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'MB Way',
                'de-DE' => 'MB Way',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Mbway::getName('en-GB'),
                'de-DE' => Mbway::getName('de-DE'),
                'fo-FO' => Mbway::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'mbway.svg',
            Mbway::getImage()
        );

        $this->assertSame(
            ['PT'],
            Mbway::getCountries()
        );

        $this->assertSame(
            ['EUR'],
            Mbway::getCurrencies()
        );
    }
}
