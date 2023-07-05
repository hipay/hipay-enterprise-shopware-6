<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;

/**
 * Mybank payment Methods.
 */
class Mybank extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'mybank';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 90;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'mybank.svg';

    protected static PaymentProduct $paymentConfig;

    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'MyBank',
            'de-DE' => 'MyBank',
        ];

        return $names[$lang] ?? null;
    }

    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order by bank transfert with MyBank.',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit MyBank.',
        ];

        return $descriptions[$lang] ?? null;
    }

    public static function getCurrencies(): ?array
    {
        return ['EUR'];
    }

    public static function getCountries(): ?array
    {
        return ['IT'];
    }
}
