<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Fullservice\Gateway\Request\PaymentMethod\IssuerBankIDPaymentMethod;
use HiPay\Payment\PaymentMethod\Ideal;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IdealTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [
            'issuer_bank_id' => static::class,
            'payment_product' => 'Ideal',
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

        $orderRequest = $this->getHostedFiledsOrderRequest(Ideal::class, $response);
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
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Ideal::class);

        $this->assertSame(
            Ideal::PAYMENT_NAME,
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            60,
            Ideal::getPosition()
        );

        $this->assertEquals(
            ['haveHostedFields' => true, 'allowPartialCapture' => true, 'allowPartialRefund' => true],
            Ideal::addDefaultCustomFields()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order by bank transfert with iDEAL',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit iDEAL',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Ideal::getDescription('en-GB'),
                'de-DE' => Ideal::getDescription('de-DE'),
                'fo-FO' => Ideal::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Ideal',
                'de-DE' => 'Ideal',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Ideal::getName('en-GB'),
                'de-DE' => Ideal::getName('de-DE'),
                'fo-FO' => Ideal::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'ideal.svg',
            Ideal::getImage()
        );

        $currencyId = 'EURO';
        $countryId = 'NETHERLANDS';

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

        $rule = Ideal::getRule($container);
        $andId = $rule['conditions'][0]['id'];

        $this->assertSame(
            [
                'name' => 'Ideal rule (only EUR from Netherlands)',
                'description' => 'Specific rule for Ideal : currency in Euro for Netherlands only',
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
