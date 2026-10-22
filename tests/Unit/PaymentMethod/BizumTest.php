<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Bizum;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;

class BizumTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [
            'phone' => '+34612345678',
        ];

        $orderRequest = $this->getHostedFiledsOrderRequest(Bizum::class, $response);

        $this->assertSame(
            Bizum::getProductCode(),
            $orderRequest->payment_product
        );

        $this->assertSame(
            '+34612345678',
            $orderRequest->paymentMethod->phone
        );

        $this->assertSame(
            '+34612345678',
            $orderRequest->customerBillingInfo->phone
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Bizum::class);

        $this->assertSame(
            Bizum::getProductCode(),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            [],
            Bizum::addDefaultCustomFields()
        );

        $this->assertEquals(
            ['haveHostedFields' => true, 'allowPartialCapture' => false, 'allowPartialRefund' => true, 'forceHostedFields' => false],
            Bizum::getConfig()
        );

        $this->assertEquals(
            75,
            Bizum::getPosition()
        );

        $this->assertEquals(
            'hipay-bizum',
            Bizum::getTechnicalName()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order with the Bizum application',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung mit der Bizum Anwendung',
                'es-ES' => 'Paga tu pedido con la aplicación Bizum',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Bizum::getDescription('en-GB'),
                'de-DE' => Bizum::getDescription('de-DE'),
                'es-ES' => Bizum::getDescription('es-ES'),
                'fo-FO' => Bizum::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Bizum',
                'de-DE' => 'Bizum',
                'es-ES' => 'Bizum',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Bizum::getName('en-GB'),
                'de-DE' => Bizum::getName('de-DE'),
                'es-ES' => Bizum::getName('es-ES'),
                'fo-FO' => Bizum::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'bizum.svg',
            Bizum::getImage()
        );

        $this->assertSame(
            ['ES'],
            Bizum::getCountries()
        );

        $this->assertSame(
            ['EUR'],
            Bizum::getCurrencies()
        );
    }
}