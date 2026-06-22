<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;

/**
 * Klarna payment method.
 */
class Klarna extends AbstractPaymentMethod
{
    protected const PAYMENT_CODE = 'klarna';

    protected const PAYMENT_POSITION = 12;

    protected const PAYMENT_IMAGE = 'klarna.svg';

    protected const TECHNICAL_NAME = 'klarna';

    /** @var PaymentProduct Configuration loaded from the json file. */
    protected static PaymentProduct $paymentConfig;

    public static function getConfig(): array
    {
        $config = parent::getConfig();
        $config['forceBasket'] = 1;

        return $config;
    }

    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Klarna',
            'de-DE' => 'Klarna',
        ];

        return $names[$lang] ?? null;
    }

    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order with Klarna',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung mit Klarna.',
        ];

        return $descriptions[$lang] ?? null;
    }

    public static function getCurrencies(): ?array
    {
        return ['EUR', 'CHF', 'DKK', 'GBP', 'NOK', 'PLN', 'SEK'];
    }

    public static function getCountries(): ?array
    {
        return ['DE', 'AT', 'BE', 'DK', 'ES', 'FI', 'FR', 'IT', 'NO', 'NL', 'PL', 'PT', 'GB', 'SE', 'CH'];
    }

    public static function requiresBasket(): bool
    {
        return true;
    }
}
