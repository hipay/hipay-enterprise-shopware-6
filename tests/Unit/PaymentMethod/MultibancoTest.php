<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Multibanco;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MultibancoTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [
            'reference_to_pay' => [
                'reference' => 'FOO',
                'amount' => '12.34',
                'expirationDate' => '0000-00-00'
            ]
        ];
        

        $orderRequest = $this->getHostedFiledsOrderRequest(
            Multibanco::class,
            $response,
            null,
            [$this->createMock(EntityRepository::class)],
            ['transaction.payment_method.custom_fields' => ['expiration_limit' => '0']]
        );

        $this->assertSame(
            Multibanco::PAYMENT_NAME,
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
            Multibanco::PAYMENT_NAME,
            $hostedPaymentPageRequest->payment_product_list            
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            ['haveHostedFields' => false, 'allowPartialCapture' => true, 'allowPartialRefund' => true, 'expiration_limit' => '3'],
            Multibanco::addDefaultCustomFields()
        );

        $this->assertEquals(
            80,
            Multibanco::getPosition()
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

        $currencyId = 'EURO';
        $countryId = 'PL';

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

        $rule = Multibanco::getRule($container);
        $andId = $rule['conditions'][0]['id'];

        $this->assertSame(
            [
                'name' => 'Multibanco rule (only EUR from Portugal)',
            'description' => 'Specific rule for Multibanco : currency in Euro for Portugal only',
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
