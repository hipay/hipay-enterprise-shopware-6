<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Przelewy24;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Przelewy24Test extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [];

        $orderRequest = $this->getHostedFiledsOrderRequest(Przelewy24::class, $response);

        $this->assertSame(
            'przelewy24',
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Przelewy24::class);

        $this->assertSame(
            'przelewy24',
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            ['haveHostedFields' => false,  'allowPartialCapture' => false, 'allowPartialRefund' => false],
            Przelewy24::addDefaultCustomFields()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order by bank transfert with Przelewy24.',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit Przelewy24.',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Przelewy24::getDescription('en-GB'),
                'de-DE' => Przelewy24::getDescription('de-DE'),
                'fo-FO' => Przelewy24::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Przelewy24',
                'de-DE' => 'Przelewy24',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Przelewy24::getName('en-GB'),
                'de-DE' => Przelewy24::getName('de-DE'),
                'fo-FO' => Przelewy24::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'przelewy24.svg',
            Przelewy24::getImage()
        );

        $currencyId = 'ZŁOTY';
        $countryId = 'POLAND';

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

        $rule = Przelewy24::getRule($container);
        $andId = $rule['conditions'][0]['id'];

        $this->assertSame(
            [
                'name' => 'Przelewy24 rule (only PLN from Poland)',
                'description' => 'Specific rule for Przelewy24 : currency in Złoty for Poland only',
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
