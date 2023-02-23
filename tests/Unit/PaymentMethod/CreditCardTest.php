<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

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
            'card_id' => md5(static::class),
            'payment_product' => 'foo,bar',
            'device_fingerprint' => md5(static::class),
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

        $response2 = [
            'token' => static::class,
            'payment_product' => 'foo',
            'card_id' => md5(static::class),
        ];

        $orderRequest = $this->getHostedFiledsOrderRequest(CreditCard::class, $response);
        $orderRequest2 = $this->getHostedFiledsOrderRequest(CreditCard::class, $response2);

        $this->assertSame(
            $response['token'],
            $orderRequest->paymentMethod->cardtoken
        );
        $this->assertSame(
            $response2['token'],
            $orderRequest2->paymentMethod->cardtoken
        );

        $this->assertSame(
            7,
            $orderRequest->paymentMethod->eci
        );
        $this->assertSame(
            7,
            $orderRequest2->paymentMethod->eci
        );

        $this->assertSame(
            $response['payment_product'],
            $orderRequest->payment_product
        );
        $this->assertSame(
            $response2['payment_product'],
            $orderRequest2->payment_product
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
            10,
            CreditCard::getPosition()
        );

        $this->assertEquals(
            [
                'haveHostedFields' => true,
                'allowPartialCapture' => true,
                'allowPartialRefund' => true,
            ],
            CreditCard::getConfig()
        );

        $this->assertEquals(
            ['cards' => ['cb', 'visa', 'mastercard', 'american-express', 'bcmc', 'maestro']],
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

        $this->assertSame(
            'credit_card.svg',
            CreditCard::getImage()
        );

        $this->assertSame(
            null,
            CreditCard::getCountries()
        );

        $this->assertSame(
            null,
            CreditCard::getCurrencies()
        );
    }
}
