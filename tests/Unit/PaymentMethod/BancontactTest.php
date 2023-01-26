<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Bancontact;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;

class BancontactTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $orderRequest = $this->getHostedFiledsOrderRequest(Bancontact::class);

        $this->assertSame(
            Bancontact::getProductCode(),
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Bancontact::class);

        $this->assertSame(
            Bancontact::getProductCode(),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            110,
            Bancontact::getPosition()
        );

        $this->assertEquals(
            ['haveHostedFields' => false, 'allowPartialCapture' => false, 'allowPartialRefund' => true],
            Bancontact::addDefaultCustomFields()
        );

        // $this->assertSame(
        //     [
        //         'en-GB' => 'Pay your order with your Credit card or by QR code with the application Bancontact.',
        //         'de-DE' => 'Bezahlen Sie Ihre Bestellung mit Ihrer Kreditkarte oder per QR-Code mit der Anwendung Bancontact.',
        //         'fo-FO' => null,
        //     ],
        //     [
        //         'en-GB' => Bancontact::getDescription('en-GB'),
        //         'de-DE' => Bancontact::getDescription('de-DE'),
        //         'fo-FO' => Bancontact::getDescription('fo-FO'),
        //     ]
        // );

        $this->assertSame(
            [
                'en-GB' => 'Bancontact',
                'de-DE' => 'Bancontact',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Bancontact::getName('en-GB'),
                'de-DE' => Bancontact::getName('de-DE'),
                'fo-FO' => Bancontact::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'bancontact.svg',
            Bancontact::getImage()
        );

        $this->assertSame(
            ['BE'],
            Bancontact::getCountries()
        );

        $this->assertSame(
            ['EUR'],
            Bancontact::getCurrencies()
        );
    }
}
