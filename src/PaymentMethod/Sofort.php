<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sofort payment Methods.
 */
class Sofort extends AbstractPaymentMethod
{
    public const PAYMENT_NAME = 'sofort-uberweisung';

    public static bool $haveHostedFields = false;

    public static bool $allowPartialCapture = false;

    /** {@inheritDoc} */
    public static function getPosition(): int
    {
        return 50;
    }

    /** {@inheritDoc} */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Sofort',
            'de-DE' => 'Sofort',
        ];

        return $names[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order by bank transfert with Sofort.',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit Sofort.',
        ];

        return $descriptions[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getImage(): ?string
    {
        return 'sofort-uberweisung.svg';
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
        $countryIds = $countryRepo->searchIds(
            (new Criteria())->addFilter(new OrFilter([
                new EqualsFilter('iso', 'BE'), // Belgium
                new EqualsFilter('iso', 'FR'), // France
                new EqualsFilter('iso', 'GP'), // Guadeloupe
                new EqualsFilter('iso', 'GF'), // French Guyana
                new EqualsFilter('iso', 'IT'), // Italy
                new EqualsFilter('iso', 'RE'), // Reunion Island
                new EqualsFilter('iso', 'MA'), // Morocco
                new EqualsFilter('iso', 'MC'), // Monaco
                new EqualsFilter('iso', 'PT'), // Portugal
                new EqualsFilter('iso', 'MQ'), // Martinique
                new EqualsFilter('iso', 'YT'), // Mayotte
                new EqualsFilter('iso', 'NC'), // New Caledonia
                new EqualsFilter('iso', 'SP'), // Spain
                new EqualsFilter('iso', 'CH'), // Switzerland
            ])),
            Context::createDefaultContext()
        )->getIds();

        return [
            'name' => 'Sofort rule (only EUR, country in description)',
            'description' => 'Specific rule for Sofort : currency in Euro for Belgium, France, Guadeloupe, '
                .'French Guyana, Italy, Reunion Island, Morocco, Monaco, Portugal, Martinique, Mayotte, New Caledonia, '
                .' Spain and Switzerland only',
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
                            'countryIds' => $countryIds,
                        ],
                    'parentId' => $andId,
                ],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, AsyncPaymentTransactionStruct $transaction): OrderRequest
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
