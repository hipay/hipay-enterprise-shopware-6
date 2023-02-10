<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;

/**
 * Mybank payment Methods.
 */
class Przelewy24 extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'przelewy24';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 100;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'przelewy24.svg';

    /** {@inheritDoc} */
    protected static PaymentProduct $paymentConfig;

    /**
     * {@inheritDoc}
     */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Przelewy24',
            'de-DE' => 'Przelewy24',
        ];

        return $names[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order by bank transfert with Przelewy24.',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit Przelewy24.',
        ];

        return $descriptions[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function getCurrencies(): ?array
    {
        return ['PLN'];
    }

    /**
     * {@inheritDoc}
     */
    public static function getCountries(): ?array
    {
        return ['PL'];
    }
}
