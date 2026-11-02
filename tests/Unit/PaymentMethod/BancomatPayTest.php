<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\BancomatPay;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;

class BancomatPayTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [
            'phone' => '+393123456789',
        ];

        $orderRequest = $this->getHostedFiledsOrderRequest(BancomatPay::class, $response);

        $this->assertSame(
            BancomatPay::getProductCode(),
            $orderRequest->payment_product
        );

        $this->assertSame(
            '+393123456789',
            $orderRequest->paymentMethod->phone
        );

        $this->assertSame(
            '+393123456789',
            $orderRequest->customerBillingInfo->phone
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(BancomatPay::class);

        $this->assertSame(
            BancomatPay::getProductCode(),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            [],
            BancomatPay::addDefaultCustomFields()
        );

        $this->assertEquals(
            ['haveHostedFields' => true, 'allowPartialCapture' => false, 'allowPartialRefund' => true, 'forceHostedFields' => false],
            BancomatPay::getConfig()
        );

        $this->assertEquals(
            80,
            BancomatPay::getPosition()
        );

        $this->assertEquals(
            'hipay-bancomatpay',
            BancomatPay::getTechnicalName()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order with the Bancomat Pay application',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung mit der Bancomat Pay Anwendung',
                'it-IT' => "Paga il tuo ordine con l'applicazione Bancomat Pay",
                'fo-FO' => null,
            ],
            [
                'en-GB' => BancomatPay::getDescription('en-GB'),
                'de-DE' => BancomatPay::getDescription('de-DE'),
                'it-IT' => BancomatPay::getDescription('it-IT'),
                'fo-FO' => BancomatPay::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Bancomat Pay',
                'de-DE' => 'Bancomat Pay',
                'it-IT' => 'Bancomat Pay',
                'fo-FO' => null,
            ],
            [
                'en-GB' => BancomatPay::getName('en-GB'),
                'de-DE' => BancomatPay::getName('de-DE'),
                'it-IT' => BancomatPay::getName('it-IT'),
                'fo-FO' => BancomatPay::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'bancomatpay.svg',
            BancomatPay::getImage()
        );

        $this->assertSame(
            ['IT', 'VA', 'SM'],
            BancomatPay::getCountries()
        );

        $this->assertSame(
            ['EUR'],
            BancomatPay::getCurrencies()
        );
    }
}