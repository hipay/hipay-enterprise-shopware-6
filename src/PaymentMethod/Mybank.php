<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mybank payment Methods.
 */
class Mybank extends AbstractPaymentMethod
{
    public const PAYMENT_NAME = 'mybank';

    public static bool $haveHostedFields = false;

    /** {@inheritDoc} */
    public static function getPosition(): int
    {
        return 90;
    }

    /** {@inheritDoc} */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'MyBank',
            'de-DE' => 'MyBank',
        ];

        return $names[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order by bank transfert with MyBank.',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit MyBank.',
        ];

        return $descriptions[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getImage(): ?string
    {
        return 'mybank.svg';
    }

    /** {@inheritDoc} */
    public static function getRule(ContainerInterface $container): ?array
    {
        /** @var EntityRepository */
        $currencyRepo = $container->get('currency.repository');
        $currencyId = $currencyRepo->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('isoCode', 'EUR')),
            Context::createDefaultContext()
        )->firstId();

        /** @var EntityRepository */
        $countryRepo = $container->get('country.repository');
        $countryId = $countryRepo->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('iso', 'IT')),
            Context::createDefaultContext()
        )->firstId();

        return [
            'name' => 'MyBank rule (only EUR from Italy)',
            'description' => 'Specific rule for MyBank : currency in Euro for Italy only',
            'priority' => 1,
            'conditions' => [
                [
                    'id' => $andId = Uuid::randomHex(),
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
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload): OrderRequest
    {
        $orderRequest->payment_product = static::PAYMENT_NAME;

        return $orderRequest;
    }

    /**
     * {@inheritDoc}
     */
    protected function hydrateHostedPage(HostedPaymentPageRequest $orderRequest, AsyncPaymentTransactionStruct $transaction): HostedPaymentPageRequest
    {
        $orderRequest->payment_product_list = static::PAYMENT_NAME;

        return $orderRequest;
    }
}
