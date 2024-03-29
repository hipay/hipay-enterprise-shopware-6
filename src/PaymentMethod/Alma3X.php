<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;

/**
 * Alma 3x payment Methods.
 */
class Alma3X extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'alma-3x';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 120;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'alma-3x.svg';

    protected static PaymentProduct $paymentConfig;

    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Alma 3x',
            'de-DE' => 'Alma 3x',
        ];

        return $names[$lang] ?? null;
    }

    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order in 3 free instalments with Alma.',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung in 3 kostenlosen Raten mit Alma.',
        ];

        return $descriptions[$lang] ?? null;
    }

    public static function getCurrencies(): ?array
    {
        return ['EUR'];
    }

    public static function getCountries(): ?array
    {
        return ['FR', 'DE', 'IT', 'BE', 'LU', 'NL', 'IE', 'AT', 'PT', 'ES'];
    }

    public static function getMinAmount(): ?float
    {
        return 50;
    }

    public static function getMaxAmount(): ?float
    {
        return 2000;
    }
}
