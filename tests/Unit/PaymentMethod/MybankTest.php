<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Mybank;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;

class MybankTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [];

        $orderRequest = $this->getHostedFiledsOrderRequest(Mybank::class, $response);

        $this->assertSame(
            Mybank::getProductCode(),
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Mybank::class);

        $this->assertSame(
            Mybank::getProductCode(),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            90,
            Mybank::getPosition()
        );

        $this->assertEquals(
            [],
            Mybank::addDefaultCustomFields()
        );

        $this->assertEquals(
            ['haveHostedFields' => false,  'allowPartialCapture' => false, 'allowPartialRefund' => true],
            Mybank::getCOnfig()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order by bank transfert with MyBank.',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit MyBank.',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Mybank::getDescription('en-GB'),
                'de-DE' => Mybank::getDescription('de-DE'),
                'fo-FO' => Mybank::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'MyBank',
                'de-DE' => 'MyBank',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Mybank::getName('en-GB'),
                'de-DE' => Mybank::getName('de-DE'),
                'fo-FO' => Mybank::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'mybank.svg',
            Mybank::getImage()
        );

        $this->assertSame(
            ['IT'],
            Mybank::getCountries()
        );

        $this->assertSame(
            ['EUR'],
            Mybank::getCurrencies()
        );
    }
}
