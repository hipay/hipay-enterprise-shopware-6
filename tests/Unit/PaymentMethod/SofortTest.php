<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Sofort;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SofortTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [];

        $orderRequest = $this->getHostedFiledsOrderRequest(Sofort::class, $response);

        $this->assertSame(
            Sofort::PAYMENT_NAME,
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Sofort::class);

        $this->assertSame(
            Sofort::PAYMENT_NAME,
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            ['haveHostedFields' => false, 'allowPartialCapture' => false, 'allowPartialRefund' => true],
            Sofort::addDefaultCustomFields()
        );

        $this->assertEquals(
            50,
            Sofort::getPosition()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order by bank transfert with Sofort.',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit Sofort.',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Sofort::getDescription('en-GB'),
                'de-DE' => Sofort::getDescription('de-DE'),
                'fo-FO' => Sofort::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Sofort',
                'de-DE' => 'Sofort',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Sofort::getName('en-GB'),
                'de-DE' => Sofort::getName('de-DE'),
                'fo-FO' => Sofort::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'sofort-uberweisung.svg',
            Sofort::getImage()
        );

        $currencyId = 'EURO';
        $countryIds = ['countryIds'];

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

        $rule = Sofort::getRule($container);
        $andId = $rule['conditions'][0]['id'];

        $this->assertSame(
            [
                'name' => 'Sofort rule (only EUR, country in description)',
                'description' => 'Specific rule for Sofort : currency in Euro for Belgium, France, Guadeloupe, '
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
