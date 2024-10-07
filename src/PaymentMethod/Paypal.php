<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
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
            $orderRequest->provider_data = json_encode($providerData);
        }

        return $orderRequest;
    }

    protected function hydrateHostedPage(
        HostedPaymentPageRequest $orderRequest,
        AsyncPaymentTransactionStruct $transaction
    ): HostedPaymentPageRequest
    {
        $customFields = $transaction->getOrderTransaction()->getPaymentMethod()->getCustomFields();

        $paymentMethod = new ExpirationLimitPaymentMethod();
        $paymentMethod->expiration_limit = intval($customFields['expiration_limit'] ?? 3);
        $orderRequest->paymentMethod = $paymentMethod;

        $orderRequest->paypal_v2_label = $customFields['label'] ?? null;
        $orderRequest->paypal_v2_shape = $customFields['shape'] ?? null;
        $orderRequest->paypal_v2_color = $customFields['color'] ?? null;
        $orderRequest->paypal_v2_height = (int) $customFields['height'] ?? null;
        $orderRequest->paypal_v2_bnpl = (int) $customFields['bnpl'] ?? null;

        return $orderRequest;
    }
}
