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
class Przelewy24 extends AbstractPaymentMethod
{
    public const PAYMENT_NAME = 'przelewy24';

    public static bool $haveHostedFields = false;

    protected static bool $allowPartialCapture = false;

    /** {@inheritDoc} */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Przelewy24',
            'de-DE' => 'Przelewy24',
        ];

        return $names[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order by bank transfert with Przelewy24.',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit Przelewy24.',
        ];

        return $descriptions[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getImage(): ?string
    {
        return 'przelewy24.svg';
    }

    /** {@inheritDoc} */
    public static function getRule(ContainerInterface $container): ?array
    {
        /** @var EntityRepository */
        $currencyRepo = $container->get('currency.repository');
        $currencyId = $currencyRepo->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('isoCode', 'PLN')),
            Context::createDefaultContext()
        )->firstId();

        /** @var EntityRepository */
        $countryRepo = $container->get('country.repository');
        $countryId = $countryRepo->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('iso', 'PL')),
            Context::createDefaultContext()
        )->firstId();

        return [
            'name' => 'Przelewy24 rule (only PLN from Poland)',
            'description' => 'Specific rule for Przelewy24 : currency in Złoty for Poland only',
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
