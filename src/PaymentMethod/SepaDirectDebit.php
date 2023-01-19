<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\SEPADirectDebitPaymentMethod;
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
 * SepaDirectDebit payment Methods.
 */
class SepaDirectDebit extends AbstractPaymentMethod
{
    public const PAYMENT_NAME = 'sdd';

    public static bool $haveHostedFields = true;

    /** {@inheritDoc} */
    public static function getPosition(): int
    {
        return 30;
    }

    /** {@inheritDoc} */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'SEPA Direct Debit',
            'de-DE' => 'SEPA Direct Debit',
        ];

        return $names[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'We\'ll automatically debit the amount from your bank account.',
            'de-DE' => 'Wir werden den Betrag automatisch von Ihrem Bankkonto abbuchen.',
        ];

        return $descriptions[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getImage(): ?string
    {
        return 'sepa-direct-debit.svg';
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
            'name' => 'Sepa Direct Debit rule (only EUR, country in description)',
            'description' => 'Specific rule for Sepa Direct Debit : currency in Euro for Belgium, France, Guadeloupe, '
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
    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload): OrderRequest
    {
        $paymentMethod = new SEPADirectDebitPaymentMethod();

        $paymentMethod->firstname = $payload['firstname'];
        $paymentMethod->lastname = $payload['lastname'];
        $paymentMethod->iban = $payload['iban'];
        $paymentMethod->gender = $payload['gender'];
        $paymentMethod->bank_name = $payload['bank_name'];
        $paymentMethod->recurring_payment = 0;

        // @phpstan-ignore-next-line
        $orderRequest->paymentMethod = $paymentMethod;
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
