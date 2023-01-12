<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Fullservice\Gateway\Request\PaymentMethod\IssuerBankIDPaymentMethod;
use HiPay\Payment\PaymentMethod\Giropay;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GyropayTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [
            'issuer_bank_id' => static::class,
            'payment_product' => 'giropay',
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

        $orderRequest = $this->getHostedFiledsOrderRequest(Giropay::class, $response);
        $this->assertInstanceOf(
            IssuerBankIDPaymentMethod::class,
            $orderRequest->paymentMethod
        );

        $this->assertSame(
            static::class,
            $orderRequest->paymentMethod->issuer_bank_id
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Giropay::class);

        $this->assertSame(
            'giropay',
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            ['haveHostedFields' => true, 'allowPartialCapture' => true],
            Giropay::addDefaultCustomFields()
        );

        $this->assertSame(
            [
                'en-GB' => 'Giropay is a very popular bank transfer payment method in Germany',
                'de-DE' => 'Giropay ist eine sehr beliebte Zahlungsmethode für Banküberweisungen in Deutschland',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Giropay::getDescription('en-GB'),
                'de-DE' => Giropay::getDescription('de-DE'),
                'fo-FO' => Giropay::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Giropay',
                'de-DE' => 'Giropay',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Giropay::getName('en-GB'),
                'de-DE' => Giropay::getName('de-DE'),
                'fo-FO' => Giropay::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'giropay.svg',
            Giropay::getImage()
        );

        $currencyId = 'EURO';
        $countryId = 'GERMANY';

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

        $rule = Giropay::getRule($container);
        $andId = $rule['conditions'][0]['id'];

        $this->assertSame(
            [
                'name' => 'Giropay rule (only EUR from Germany)',
                'description' => 'Specific rule for giropay : currency in Euro for Germany only',
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
