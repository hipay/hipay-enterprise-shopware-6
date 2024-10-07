<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Paypal;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class PaypalTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testHydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(
            Paypal::class,
            null,
            ['transaction.payment_method.custom_fields' =>
                ['color' => 'gold'],
                ['shape' => 'pill'],
                ['label' => 'paypal'],
                ['height' => '40'],
                ['bnpl' => true]
            ],
            [$this->createMock(EntityRepository::class)]
        );

        $this->assertSame(
            'paypal',
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testhydrateFields()
    {
        $response = ['payment_product' => 'paypal'];

        $orderRequest = $this->getHostedFiledsOrderRequest(
            Paypal::class,
            $response,
            null,
            [$this->createMock(EntityRepository::class)],
        );

        $this->assertSame(
            'paypal',
            $orderRequest->payment_product
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            [
                'color' => 'gold',
                'shape' => 'pill',
                'label' => 'paypal',
                'height' => '40',
                'bnpl' => true,
            ],
            Paypal::addDefaultCustomFields()
        );

        $this->assertEquals(
            ['haveHostedFields' => false, 'allowPartialCapture' => true, 'allowPartialRefund' => true],
            Paypal::getConfig()
        );

        $this->assertEquals(
            20,
            Paypal::getPosition()
        );

        $this->assertEquals(
            'hipay-paypal',
            Paypal::getTechnicalName()
        );

        $this->assertSame(
            [
                'en-GB' => 'PayPal is an American company offering an online payment service system worldwide',
                'de-DE' => 'PayPal ist ein amerikanisches Unternehmen, das weltweit ein Online-Zahlungsdienstsystem anbietet',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Paypal::getDescription('en-GB'),
                'de-DE' => Paypal::getDescription('de-DE'),
                'fo-FO' => Paypal::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Paypal',
                'de-DE' => 'Paypal',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Paypal::getName('en-GB'),
                'de-DE' => Paypal::getName('de-DE'),
                'fo-FO' => Paypal::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'paypal.svg',
            Paypal::getImage()
        );

        $this->assertSame(
            null,
            Paypal::getCountries()
        );

        $this->assertSame(
            null,
            Paypal::getCurrencies()
        );
    }
}
