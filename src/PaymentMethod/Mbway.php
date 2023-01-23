<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\PhonePaymentMethod;
use libphonenumber\PhoneNumberFormat;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Credit card payment Methods.
 */
class Mbway extends AbstractPaymentMethod
{
    public const PAYMENT_NAME = 'mbway';

    public static bool $haveHostedFields = true;

    /** {@inheritDoc} */
    public static function getPosition(): int
    {
        return 70;
    }

    /** {@inheritDoc} */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'MB Way',
            'de-DE' => 'MB Way',
        ];

        return $names[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order with the MB Way application',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung mit der MB Way Anwendung',
        ];

        return $descriptions[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getImage(): ?string
    {
        return 'mbway.svg';
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
            (new Criteria())->addFilter(new EqualsFilter('iso', 'PT')),
            Context::createDefaultContext()
        )->firstId();

        return [
            'name' => 'MB way rule (only EUR from Portugal)',
            'description' => 'Specific rule for MB way : currency in Euro for Portugal only',
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
     *
     * @throws BadRequestException
     */
    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, AsyncPaymentTransactionStruct $transaction): OrderRequest
    {
        $paymentMethod = new PhonePaymentMethod();
        $paymentMethod->phone = $this->formatPhoneNumber(
            $payload['phone'],
            $orderRequest->customerBillingInfo->country,
            PhoneNumberFormat::NATIONAL
        );
        // @phpstan-ignore-next-line
        $orderRequest->paymentMethod = $paymentMethod;
        $orderRequest->payment_product = static::PAYMENT_NAME;

        $orderRequest->customerBillingInfo->phone = $paymentMethod->phone;

        return $orderRequest;
    }

    /**
     * {@inheritDoc}
     *
     * @throws BadRequestException
     */
    protected function hydrateHostedPage(HostedPaymentPageRequest $orderRequest, AsyncPaymentTransactionStruct $transaction): HostedPaymentPageRequest
    {
        $orderRequest->customerBillingInfo->phone = $this->formatPhoneNumber(
            $orderRequest->customerBillingInfo->phone,
            $orderRequest->customerBillingInfo->country,
            PhoneNumberFormat::NATIONAL
        );
        $orderRequest->payment_product_list = static::PAYMENT_NAME;

        return $orderRequest;
    }
}
