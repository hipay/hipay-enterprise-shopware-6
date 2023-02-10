<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Sofort;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;

class SofortTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [];

        $orderRequest = $this->getHostedFiledsOrderRequest(Sofort::class, $response);

        $this->assertSame(
            Sofort::getProductCode(),
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Sofort::class);

        $this->assertSame(
            Sofort::getProductCode(),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            ['haveHostedFields' => false, 'allowPartialCapture' => false, 'allowPartialRefund' => true],
            Sofort::addDefaultCustomFields()
        );

        $this->assertEquals(
            50,
            Sofort::getPosition()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order by bank transfert with Sofort.',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit Sofort.',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Sofort::getDescription('en-GB'),
                'de-DE' => Sofort::getDescription('de-DE'),
                'fo-FO' => Sofort::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Sofort',
                'de-DE' => 'Sofort',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Sofort::getName('en-GB'),
                'de-DE' => Sofort::getName('de-DE'),
                'fo-FO' => Sofort::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'sofort-uberweisung.svg',
            Sofort::getImage()
        );

        $this->assertSame(
            ['BE', 'FR', 'GP', 'GF', 'IT', 'RE', 'MA', 'MC', 'PT', 'MQ', 'YT', 'NC', 'SP', 'CH'],
            Sofort::getCountries()
        );

        $this->assertSame(
            ['EUR'],
            Sofort::getCurrencies()
        );
    }
}
