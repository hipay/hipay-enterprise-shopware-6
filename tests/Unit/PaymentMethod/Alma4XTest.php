<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Alma4X;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;

class Alma4XTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [];

        $orderRequest = $this->getHostedFiledsOrderRequest(Alma4X::class, $response);

        $this->assertSame(
            Alma4X::getProductCode(),
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Alma4X::class);

        $this->assertSame(
            Alma4X::getProductCode(),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            [],
            Alma4X::addDefaultCustomFields()
        );

        $this->assertEquals(
            ['haveHostedFields' => false, 'allowPartialCapture' => false, 'allowPartialRefund' => true],
            Alma4X::getConfig()
        );

        $this->assertEquals(
            121,
            Alma4X::getPosition()
        );

        $this->assertEquals(
            'hipay-alma-4x',
            Alma4X::getTechnicalName()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order in 4 free instalments with Alma.',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung in 4 kostenlosen Raten mit Alma.',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Alma4X::getDescription('en-GB'),
                'de-DE' => Alma4X::getDescription('de-DE'),
                'fo-FO' => Alma4X::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Alma 4x',
                'de-DE' => 'Alma 4x',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Alma4X::getName('en-GB'),
                'de-DE' => Alma4X::getName('de-DE'),
                'fo-FO' => Alma4X::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'alma-4x.svg',
            Alma4X::getImage()
        );

        $this->assertSame(
            ['FR', 'DE', 'IT', 'BE', 'LU', 'NL', 'IE', 'AT', 'PT', 'ES'],
            Alma4X::getCountries()
        );

        $this->assertSame(
            ['EUR'],
            Alma4X::getCurrencies()
        );
    }
}