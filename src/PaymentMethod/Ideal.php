<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\IssuerBankIDPaymentMethod;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;

/**
 * Ideal payment Methods.
 */
class Ideal extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'ideal';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 60;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'ideal.svg';

    /** {@inheritDoc} */
    protected static PaymentProduct $paymentConfig;

    /**
     * {@inheritDoc}
     */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Ideal',
            'de-DE' => 'Ideal',
        ];

        return $names[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order by bank transfert with iDEAL',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit iDEAL',
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
        return ['NL'];
    }

    /**
     * {@inheritDoc}
     */
    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, AsyncPaymentTransactionStruct $transaction): OrderRequest
    {
        $paymentMethod = new IssuerBankIDPaymentMethod();
        $paymentMethod->issuer_bank_id = $payload['issuer_bank_id'];

        $orderRequest->paymentMethod = $paymentMethod;

        return $orderRequest;
    }
}
