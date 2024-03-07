<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;

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

    protected static PaymentProduct $paymentConfig;

    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Paypal',
            'de-DE' => 'Paypal',
        ];

        return $names[$lang] ?? null;
    }

    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'PayPal is an American company offering an online payment service system worldwide',
            'de-DE' => 'PayPal ist ein amerikanisches Unternehmen, das weltweit ein Online-Zahlungsdienstsystem anbietet',
        ];

        return $descriptions[$lang] ?? null;
    }

    public static function addDefaultCustomFields(): array
    {
        return [
            'merchantPayPalId' => '',
            'color' => 'gold',
            'shape' => 'pill',
            'label' => 'paypal',
            'height' => '40',
            'bnpl' => true,
        ];
    }

    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, AsyncPaymentTransactionStruct $transaction): OrderRequest
    {
        if ('paypal' === $orderRequest->payment_product && isset($payload['orderID'])) {
            $providerData = ['paypal_id' => $payload['orderID']];
            $orderRequest->provider_data = (string) json_encode($providerData);
        }

        return $orderRequest;
    }
}
