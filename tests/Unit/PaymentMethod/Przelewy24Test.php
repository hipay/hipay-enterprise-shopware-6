<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Przelewy24;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;

class Przelewy24Test extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [];

        $orderRequest = $this->getHostedFiledsOrderRequest(Przelewy24::class, $response);

        $this->assertSame(
            'przelewy24',
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Przelewy24::class);

        $this->assertSame(
            'przelewy24',
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            [],
            Przelewy24::addDefaultCustomFields()
        );

        $this->assertEquals(
            ['haveHostedFields' => false,  'allowPartialCapture' => false, 'allowPartialRefund' => false],
            Przelewy24::getConfig()
        );

        $this->assertEquals(
            100,
            Przelewy24::getPosition()
        );

        $this->assertEquals(
            'hipay-przelewy24',
            Przelewy24::getTechnicalName()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order by bank transfert with Przelewy24.',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit Przelewy24.',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Przelewy24::getDescription('en-GB'),
                'de-DE' => Przelewy24::getDescription('de-DE'),
                'fo-FO' => Przelewy24::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Przelewy24',
                'de-DE' => 'Przelewy24',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Przelewy24::getName('en-GB'),
                'de-DE' => Przelewy24::getName('de-DE'),
                'fo-FO' => Przelewy24::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'przelewy24.svg',
            Przelewy24::getImage()
        );

        $this->assertSame(
            ['PL'],
            Przelewy24::getCountries()
        );

        $this->assertSame(
            ['PLN'],
            Przelewy24::getCurrencies()
        );
    }
}