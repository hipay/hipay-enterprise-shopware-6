<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;

/**
 * Paypal payment Methods.
 */
class Paypal extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'paypal';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 20;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'paypal.svg';

    /** {@inheritDoc} */
    protected static PaymentProduct $paymentConfig;

    /**
     * {@inheritDoc}
     */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Paypal',
            'de-DE' => 'Paypal',
        ];

        return $names[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'PayPal is an American company offering an online payment service system worldwide',
            'de-DE' => 'PayPal ist ein amerikanisches Unternehmen, das weltweit ein Online-Zahlungsdienstsystem anbietet',
        ];

        return $descriptions[$lang] ?? null;
    }
}
