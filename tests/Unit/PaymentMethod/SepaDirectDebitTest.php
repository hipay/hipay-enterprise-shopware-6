<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\SepaDirectDebit;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\TestCase;

class SepaDirectDebitTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [
            'firstname' => 'firstname',
            'lastname' => 'lastname',
            'iban' => 'iban',
            'gender' => 'gender',
            'bank_name' => 'bank_name',
        ];

        $orderRequest = $this->getHostedFiledsOrderRequest(SepaDirectDebit::class, $response);

        $this->assertSame(
            SepaDirectDebit::getProductCode(),
            $orderRequest->payment_product
        );

        $this->assertSame(
            $response['firstname'],
            $orderRequest->paymentMethod->firstname
        );

        $this->assertSame(
            $response['lastname'],
            $orderRequest->paymentMethod->lastname
        );

        $this->assertSame(
            $response['iban'],
            $orderRequest->paymentMethod->iban
        );

        $this->assertSame(
            $response['gender'],
            $orderRequest->paymentMethod->gender
        );

        $this->assertSame(
            $response['bank_name'],
            $orderRequest->paymentMethod->bank_name
        );

        $this->assertSame(
            0,
            $orderRequest->paymentMethod->recurring_payment
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(SepaDirectDebit::class);

        $this->assertSame(
            SepaDirectDebit::getProductCode(),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            [],
            SepaDirectDebit::addDefaultCustomFields()
        );

        $this->assertEquals(
            ['haveHostedFields' => true, 'allowPartialCapture' => false, 'allowPartialRefund' => false],
            SepaDirectDebit::getConfig()
        );

        $this->assertEquals(
            30,
            SepaDirectDebit::getPosition()
        );

        $this->assertEquals(
            'hipay-sdd',
            SepaDirectDebit::getTechnicalName()
        );

        $this->assertSame(
            [
                'en-GB' => 'We\'ll automatically debit the amount from your bank account.',
                'de-DE' => 'Wir werden den Betrag automatisch von Ihrem Bankkonto abbuchen.',
                'fo-FO' => null,
            ],
            [
                'en-GB' => SepaDirectDebit::getDescription('en-GB'),
                'de-DE' => SepaDirectDebit::getDescription('de-DE'),
                'fo-FO' => SepaDirectDebit::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'SEPA Direct Debit',
                'de-DE' => 'SEPA Direct Debit',
                'fo-FO' => null,
            ],
            [
                'en-GB' => SepaDirectDebit::getName('en-GB'),
                'de-DE' => SepaDirectDebit::getName('de-DE'),
                'fo-FO' => SepaDirectDebit::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'sepa-direct-debit.svg',
            SepaDirectDebit::getImage()
        );

        $this->assertSame(
            ['BE', 'FR', 'GP', 'GF', 'IT', 'RE', 'MA', 'MC', 'PT', 'MQ', 'YT', 'NC', 'SP', 'CH'],
            SepaDirectDebit::getCountries()
        );

        $this->assertSame(
            ['EUR'],
            SepaDirectDebit::getCurrencies()
        );
    }
}