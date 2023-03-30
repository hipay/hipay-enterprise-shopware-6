<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\IssuerBankIDPaymentMethod;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;

/**
 * Giropay payment Methods.
 */
class Giropay extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'giropay';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 40;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'giropay.svg';

    /** {@inheritDoc} */
    protected static PaymentProduct $paymentConfig;

    /**
     * {@inheritDoc}
     */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Giropay',
            'de-DE' => 'Giropay',
        ];

        return $names[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Giropay is a very popular bank transfer payment method in Germany',
            'de-DE' => 'Giropay ist eine sehr beliebte Zahlungsmethode für Banküberweisungen in Deutschland',
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
        return ['DE'];
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
