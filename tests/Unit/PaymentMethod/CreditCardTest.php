<?php

namespace Hipay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\CreditCard;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;

class CreditCardTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [
            'token' => static::class,
            'payment_product' => 'foo,bar',
            'device_fingerprint' => md5(statis::class),
            'browser_info' => [
                'http_user_agent' => 'PhpUnit',
                'java_enabled' => false,
                'javascript_enabled' => false,
                'language' => 'en-GB',
                'color_depth' => '64bits',
                'screen_height' => 768,
                'screen_width' => 1024,
                'timezone' => -120,
            ],
        ];

        $orderRequest = $this->getHostedFiledsOrderRequest(CreditCard::class, $response);

        $this->assertSame(
            $response['token'],
            $orderRequest->paymentMethod->cardtoken
        );

        $this->assertSame(
            7,
            $orderRequest->paymentMethod->eci
        );

        $this->assertSame(
            $response['payment_product'],
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $paymentProductList = ['foo', 'bar', 'quz'];

        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(
            CreditCard::class,
            null,
            ['transaction.payment_method.custom_fields' => ['cards' => $paymentProductList]]
        );

        $this->assertSame(
            implode(',', $paymentProductList),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            ['cards' => ['cb', 'visa', 'mastercard', 'american-express', 'bancontact', 'maestro']],
            CreditCard::addDefaultCustomFields()
        );

        $this->assertSame(
            [
                'en-GB' => 'Use your credit cards to safely pay through our PCI DSS certified payment provider',
                'de-DE' => 'Verwenden Sie Ihre Kreditkarten, um sicher über unseren PCI DSS-zertifizierten Zahlungsanbieter zu bezahlen',
                'fo-FO' => null,
            ],
            [
                'en-GB' => CreditCard::getDescription('en-GB'),
                'de-DE' => CreditCard::getDescription('de-DE'),
                'fo-FO' => CreditCard::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Credit Cards',
                'de-DE' => 'Kreditkarten',
                'fo-FO' => null,
            ],
            [
                'en-GB' => CreditCard::getName('en-GB'),
                'de-DE' => CreditCard::getName('de-DE'),
                'fo-FO' => CreditCard::getName('fo-FO'),
            ]
        );
    }
}