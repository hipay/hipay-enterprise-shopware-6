<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Multibanco;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class MultibancoTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [
            'reference_to_pay' => json_encode([
                'reference' => 'FOO',
                'amount' => '12.34',
                'expirationDate' => '0000-00-00',
            ]),
        ];

        $orderRequest = $this->getHostedFiledsOrderRequest(
            Multibanco::class,
            $response,
            null,
            [$this->createMock(EntityRepository::class)],
            ['transaction.payment_method.custom_fields' => ['expiration_limit' => '0']]
        );

        $this->assertSame(
            Multibanco::getProductCode(),
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(
            Multibanco::class,
            null,
            ['transaction.payment_method.custom_fields' => ['expiration_limit' => '0']],
            [$this->createMock(EntityRepository::class)],
        );

        $this->assertSame(
            Multibanco::getProductCode(),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            ['expiration_limit' => '3'],
            Multibanco::addDefaultCustomFields()
        );

        $this->assertEquals(
            ['haveHostedFields' => false, 'allowPartialCapture' => false, 'allowPartialRefund' => false],
            Multibanco::getConfig()
        );

        $this->assertEquals(
            80,
            Multibanco::getPosition()
        );

        $this->assertEquals(
            'hipay-multibanco',
            Multibanco::getTechnicalName()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order with the Multibanco',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung mit der Multibanco',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Multibanco::getDescription('en-GB'),
                'de-DE' => Multibanco::getDescription('de-DE'),
                'fo-FO' => Multibanco::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Multibanco',
                'de-DE' => 'Multibanco',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Multibanco::getName('en-GB'),
                'de-DE' => Multibanco::getName('de-DE'),
                'fo-FO' => Multibanco::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'multibanco.svg',
            Multibanco::getImage()
        );

        $this->assertSame(
            ['PT'],
            Multibanco::getCountries()
        );

        $this->assertSame(
            ['EUR'],
            Multibanco::getCurrencies()
        );
    }
}
