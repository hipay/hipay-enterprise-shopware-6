<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;

/**
 * Sofort payment Methods.
 */
class Sofort extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'sofort-uberweisung';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 50;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'sofort-uberweisung.svg';

    /** {@inheritDoc} */
    protected static PaymentProduct $paymentConfig;

    /**
     * {@inheritDoc}
     */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Sofort',
            'de-DE' => 'Sofort',
        ];

        return $names[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order by bank transfert with Sofort.',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit Sofort.',
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
        return ['BE', 'FR', 'GP', 'GF', 'IT', 'RE', 'MA', 'MC', 'PT', 'MQ', 'YT', 'NC', 'SP', 'CH'];
    }
}
