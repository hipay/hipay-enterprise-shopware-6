<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;

/**
 * Bancontact payment Methods.
 */
class Bancontact extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'bancontact';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 110;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'bancontact.svg';

    protected static PaymentProduct $paymentConfig;

    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Bancontact',
            'de-DE' => 'Bancontact',
        ];

        return $names[$lang] ?? null;
    }

    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order with your Credit card or by QR code with the application Bancontact.',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung mit Ihrer Kreditkarte oder per QR-Code mit der Anwendung Bancontact.',
        ];

        return $descriptions[$lang] ?? null;
    }

    public static function getCurrencies(): ?array
    {
        return ['EUR'];
    }

    public static function getCountries(): ?array
    {
        return ['BE'];
    }
}
