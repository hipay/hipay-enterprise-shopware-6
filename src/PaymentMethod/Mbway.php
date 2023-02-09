<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\PhonePaymentMethod;
use libphonenumber\PhoneNumberFormat;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;

/**
 * Mbway payment Methods.
 */
class Mbway extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'mbway';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 70;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'mbway.svg';

    /** {@inheritDoc} */
    protected static PaymentProduct $paymentConfig;

    /**
     * {@inheritDoc}
     */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'MB Way',
            'de-DE' => 'MB Way',
        ];

        return $names[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order with the MB Way application',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung mit der MB Way Anwendung',
        ];

        return $descriptions[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function getCurrencies(): ?array
    {
        return ['EUR'];
    }

    /**
     * {@inheritDoc}
     */
    public static function getCountries(): ?array
    {
        return ['PT'];
    }

    /**
     * {@inheritDoc}
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

        $orderRequest->customerBillingInfo->phone = $paymentMethod->phone;

        return $orderRequest;
    }

    /**
     * {@inheritDoc}
     */
    protected function hydrateHostedPage(HostedPaymentPageRequest $orderRequest, AsyncPaymentTransactionStruct $transaction): HostedPaymentPageRequest
    {
        $orderRequest->customerBillingInfo->phone = $this->formatPhoneNumber(
            $orderRequest->customerBillingInfo->phone,
            $orderRequest->customerBillingInfo->country,
            PhoneNumberFormat::NATIONAL
        );

        return $orderRequest;
    }
}
