<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;

/**
 * Klarna payment method.
 */
class Klarna extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'klarna';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 12;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'klarna.svg';

    /** {@inheritDoc} */
    protected const TECHNICAL_NAME = 'klarna';

    /** @var PaymentProduct Configuration loaded from the json file. */
    protected static PaymentProduct $paymentConfig;

    /**
     * {@inheritDoc}
     */
    public static function getConfig(): array
    {
        $config = parent::getConfig();
        $config['forceBasket'] = 1;
        return $config;
    }

    /**
     * {@inheritDoc}
     */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Klarna',
            'de-DE' => 'Klarna',
        ];

        return $names[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order with Klarna',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung mit Klarna.',
        ];

        return $descriptions[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function getCurrencies(): ?array
    {
        return ["EUR", "CHF", "DKK", "GBP", "NOK", "PLN", "SEK"];
    }

    /**
     * {@inheritDoc}
     */
    public static function getCountries(): ?array
    {
        return ["DE", "AT", "BE", "DK", "ES", "FI", "FR", "IT", "NO", "NL", "PL", "PT", "GB", "SE", "CH"];
    }

    /**
     * {@inheritDoc}
     */
    public static function requiresBasket(): bool
    {
        return true;
    }
}