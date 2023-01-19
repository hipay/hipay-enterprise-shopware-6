<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Payment\PaymentMethod\Bancontact;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BancontactTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [];

        $orderRequest = $this->getHostedFiledsOrderRequest(Bancontact::class, $response);

        $this->assertSame(
            Bancontact::PAYMENT_NAME,
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Bancontact::class);

        $this->assertSame(
            Bancontact::PAYMENT_NAME,
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            110,
            Bancontact::getPosition()
        );

        $this->assertEquals(
            ['haveHostedFields' => false, 'allowPartialCapture' => true, 'allowPartialRefund' => true],
            Bancontact::addDefaultCustomFields()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order with your Credit card or by QR code with the application Bancontact.',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung mit Ihrer Kreditkarte oder per QR-Code mit der Anwendung Bancontact.',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Bancontact::getDescription('en-GB'),
                'de-DE' => Bancontact::getDescription('de-DE'),
                'fo-FO' => Bancontact::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'Bancontact',
                'de-DE' => 'Bancontact',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Bancontact::getName('en-GB'),
                'de-DE' => Bancontact::getName('de-DE'),
                'fo-FO' => Bancontact::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'bancontact.svg',
            Bancontact::getImage()
        );

        $currencyId = 'EURO';
        $countryId = 'BELGIUM';

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

        $rule = Bancontact::getRule($container);
        $andId = $rule['conditions'][0]['id'];

        $this->assertSame(
            [
                'name' => 'Bancontact rule (only EUR in Belgium)',
                'description' => 'Specific rule for Bancontact : currency in Euro for Belgium',
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
