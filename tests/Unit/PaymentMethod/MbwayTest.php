<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Mbway;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MbwayTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [
            'phone' => '+351289094089',
        ];

        $orderRequest = $this->getHostedFiledsOrderRequest(Mbway::class, $response);

        $this->assertSame(
            Mbway::PAYMENT_NAME,
            $orderRequest->payment_product
        );

        $this->assertSame(
            '289094089',
            $orderRequest->paymentMethod->phone
        );

        $this->assertSame(
            '289094089',
            $orderRequest->customerBillingInfo->phone
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Mbway::class);

        $this->assertSame(
            Mbway::PAYMENT_NAME,
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            ['haveHostedFields' => true, 'allowPartialCapture' => true, 'allowPartialRefund' => true],
            Mbway::addDefaultCustomFields()
        );

        $this->assertEquals(
            70,
            Mbway::getPosition()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order with the MB Way application',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung mit der MB Way Anwendung',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Mbway::getDescription('en-GB'),
                'de-DE' => Mbway::getDescription('de-DE'),
                'fo-FO' => Mbway::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'MB Way',
                'de-DE' => 'MB Way',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Mbway::getName('en-GB'),
                'de-DE' => Mbway::getName('de-DE'),
                'fo-FO' => Mbway::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'mbway.svg',
            Mbway::getImage()
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

        $rule = Mbway::getRule($container);
        $andId = $rule['conditions'][0]['id'];

        $this->assertSame(
            [
                'name' => 'MB way rule (only EUR from Portugal)',
            'description' => 'Specific rule for giropay : currency in Euro for Portugal only',
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
