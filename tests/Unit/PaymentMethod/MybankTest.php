<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Mybank;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MybankTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [];

        $orderRequest = $this->getHostedFiledsOrderRequest(Mybank::class, $response);

        $this->assertSame(
            'mybank',
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Mybank::class);

        $this->assertSame(
            'mybank',
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            ['haveHostedFields' => false,  'allowPartialCapture' => true, 'allowPartialRefund' => true],
            Mybank::addDefaultCustomFields()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order by bank transfert with MyBank.',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit MyBank.',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Mybank::getDescription('en-GB'),
                'de-DE' => Mybank::getDescription('de-DE'),
                'fo-FO' => Mybank::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'MyBank',
                'de-DE' => 'MyBank',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Mybank::getName('en-GB'),
                'de-DE' => Mybank::getName('de-DE'),
                'fo-FO' => Mybank::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'mybank.svg',
            Mybank::getImage()
        );

        $currencyId = 'EURO';
        $countryId = 'ITALY';

        $repoStack = [];
        foreach (['currency.repository' => $currencyId, 'country.repository' => $countryId] as $repoName => $value) {
            /** @var IdSearchResult&MockObject */
            $result = $this->createMock(IdSearchResult::class);
            $result->method('firstId')->willreturn($value);

            /** @var EntityRepository&MockObject */
            $repo = $this->createMock(EntityRepository::class);
            $repo->method('searchIds')->willreturn($result);

            $repoStack[$repoName] = $repo;
        }

        /** @var ContainerInterface&MockObject */
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturncallback(function ($repoName) use ($repoStack) {
            return $repoStack[$repoName];
        });

        $rule = Mybank::getRule($container);
        $andId = $rule['conditions'][0]['id'];

        $this->assertSame(
            [
                'name' => 'MyBank rule (only EUR from Italy)',
                'description' => 'Specific rule for MyBank : currency in Euro for Italy only',
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
                            'countryIds' => [$countryId],
                        ],
                        'parentId' => $andId,
                    ],
                ],
            ],
            $rule
        );
    }
}
