<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Fullservice\Gateway\Request\PaymentMethod\IssuerBankIDPaymentMethod;
use HiPay\Payment\PaymentMethod\Ideal;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;

class IdealTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [
            'issuer_bank_id' => static::class,
        ];

        $orderRequest = $this->getHostedFiledsOrderRequest(Ideal::class, $response);
        $this->assertInstanceOf(
            IssuerBankIDPaymentMethod::class,
            $orderRequest->paymentMethod
        );

        $this->assertSame(
            static::class,
            $orderRequest->paymentMethod->issuer_bank_id
        );

        $this->assertSame(
            Ideal::getProductCode(),
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Ideal::class);

        $this->assertSame(
            Ideal::getProductCode(),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            60,
            Ideal::getPosition()
        );

        $this->assertEquals(
            'hipay-ideal',
            Ideal::getTechnicalName()
        );

        $this->assertEquals(
            [],
            Ideal::addDefaultCustomFields()
        );

        $this->assertEquals(
            ['haveHostedFields' => true, 'allowPartialCapture' => false, 'allowPartialRefund' => true],
            Ideal::getConfig()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order by bank transfert with iDEAL',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit iDEAL',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Ideal::getDescription('en-GB'),
                'de-DE' => Ideal::getDescription('de-DE'),
                'fo-FO' => Ideal::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Ideal',
                'de-DE' => 'Ideal',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Ideal::getName('en-GB'),
                'de-DE' => Ideal::getName('de-DE'),
                'fo-FO' => Ideal::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'ideal.svg',
            Ideal::getImage()
        );

        $this->assertSame(
            ['NL'],
            Ideal::getCountries()
        );

        $this->assertSame(
            ['EUR'],
            Ideal::getCurrencies()
        );
    }
}