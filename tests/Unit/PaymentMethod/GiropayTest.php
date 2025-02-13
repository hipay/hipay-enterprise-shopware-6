<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Fullservice\Gateway\Request\PaymentMethod\IssuerBankIDPaymentMethod;
use HiPay\Payment\PaymentMethod\Giropay;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;

class GiropayTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [
            'issuer_bank_id' => static::class,
        ];

        $orderRequest = $this->getHostedFiledsOrderRequest(Giropay::class, $response);
        $this->assertInstanceOf(
            IssuerBankIDPaymentMethod::class,
            $orderRequest->paymentMethod
        );

        $this->assertSame(
            static::class,
            $orderRequest->paymentMethod->issuer_bank_id
        );

        $this->assertSame(
            Giropay::getProductCode(),
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Giropay::class);

        $this->assertSame(
            Giropay::getProductCode(),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            40,
            Giropay::getPosition()
        );

        $this->assertEquals(
            'hipay-giropay',
            Giropay::getTechnicalName()
        );

        $this->assertEquals(
            ['haveHostedFields' => true, 'allowPartialCapture' => false, 'allowPartialRefund' => false],
            Giropay::getConfig()
        );

        $this->assertEquals(
            [],
            Giropay::addDefaultCustomFields()
        );

        $this->assertSame(
            [
                'en-GB' => 'Giropay is a very popular bank transfer payment method in Germany',
                'de-DE' => 'Giropay ist eine sehr beliebte Zahlungsmethode für Banküberweisungen in Deutschland',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Giropay::getDescription('en-GB'),
                'de-DE' => Giropay::getDescription('de-DE'),
                'fo-FO' => Giropay::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Giropay',
                'de-DE' => 'Giropay',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Giropay::getName('en-GB'),
                'de-DE' => Giropay::getName('de-DE'),
                'fo-FO' => Giropay::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'giropay.svg',
            Giropay::getImage()
        );

        $this->assertSame(
            ['DE'],
            Giropay::getCountries()
        );

        $this->assertSame(
            ['EUR'],
            Giropay::getCurrencies()
        );
    }
}
