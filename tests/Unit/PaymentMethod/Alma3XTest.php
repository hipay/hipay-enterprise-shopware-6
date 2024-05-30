<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Alma3X;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;

class Alma3XTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [];

        $orderRequest = $this->getHostedFiledsOrderRequest(Alma3X::class, $response);

        $this->assertSame(
            Alma3X::getProductCode(),
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Alma3X::class);

        $this->assertSame(
            Alma3X::getProductCode(),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            [],
            Alma3X::addDefaultCustomFields()
        );

        $this->assertEquals(
            ['haveHostedFields' => false, 'allowPartialCapture' => false, 'allowPartialRefund' => true],
            Alma3X::getConfig()
        );

        $this->assertEquals(
            120,
            Alma3X::getPosition()
        );

        $this->assertEquals(
            'hipay-alma-3x',
            Alma3X::getTechnicalName()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order in 3 free instalments with Alma.',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung in 3 kostenlosen Raten mit Alma.',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Alma3X::getDescription('en-GB'),
                'de-DE' => Alma3X::getDescription('de-DE'),
                'fo-FO' => Alma3X::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Alma 3x',
                'de-DE' => 'Alma 3x',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Alma3X::getName('en-GB'),
                'de-DE' => Alma3X::getName('de-DE'),
                'fo-FO' => Alma3X::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'alma-3x.svg',
            Alma3X::getImage()
        );

        $this->assertSame(
            ['FR', 'DE', 'IT', 'BE', 'LU', 'NL', 'IE', 'AT', 'PT', 'ES'],
            Alma3X::getCountries()
        );

        $this->assertSame(
            ['EUR'],
            Alma3X::getCurrencies()
        );
    }
}