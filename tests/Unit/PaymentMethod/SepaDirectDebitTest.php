<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\SepaDirectDebit;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
            SepaDirectDebit::PAYMENT_NAME,
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
            SepaDirectDebit::PAYMENT_NAME,
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            ['haveHostedFields' => true, 'allowPartialCapture' => true],
            SepaDirectDebit::addDefaultCustomFields()
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

        $currencyId = 'EURO';
        $countryIds = ['POLAND'];

        $repoStack = [];

        /** @var IdSearchResult&MockObject */
        $result = $this->createMock(IdSearchResult::class);
        $result->method('firstId')->willreturn($currencyId);

        /** @var EntityRepository&MockObject */
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('searchIds')->willreturn($result);

        $repoStack['currency.repository'] = $repo;

        /** @var IdSearchResult&MockObject */
        $result = $this->createMock(IdSearchResult::class);
        $result->method('getIds')->willreturn($countryIds);

        /** @var EntityRepository&MockObject */
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('searchIds')->willreturn($result);

        $repoStack['country.repository'] = $repo;

        /** @var ContainerInterface&MockObject */
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturncallback(function ($repoName) use ($repoStack) {
            return $repoStack[$repoName];
        });

        $rule = SepaDirectDebit::getRule($container);
        $andId = $rule['conditions'][0]['id'];

        $this->assertSame(
            [
                'name' => 'Sepa Direct Debit rule (only EUR, country in description)',
                'description' => 'Specific rule for Sepa Direct Debit : currency in Euro for Belgium, France, Guadeloupe, '
                    .'French Guyana, Italy, Reunion Island, Morocco, Monaco, Portugal, Martinique, Mayotte, New Caledonia, '
                    .' Spain and Switzerland only',
                'priority' => 1,
                'conditions' => [
                    [
                        'id' => $andId,
                        'type' => 'andContainer',
                        'position' => 0,
                    ],
                    [
                        'type' => 'currency',
                        'position' => 0,
                        'value' => [
                            'operator' => Rule::OPERATOR_EQ,
                            'currencyIds' => [$currencyId],
                        ],
                        'parentId' => $andId,
                    ],
                    [
                        'type' => 'customerBillingCountry',
                        'position' => 1,
                        'value' => [
                            'operator' => Rule::OPERATOR_EQ,
                            'countryIds' => $countryIds,
                        ],
                        'parentId' => $andId,
                    ],
                ],
            ],
            $rule
        );
    }
}
