<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\CardTokenPaymentMethod;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

/**
 * GooglePay payment Methods.
 */
class GooglePay extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const TECHNICAL_NAME = 'googlepay';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 6;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'googlepay.svg';

    /**
     * Fixed 3-D Secure authentication indicator (3DS if available),
     * independent from the "Authentication Indicator" setting. To be validated.
     */
    private const AUTHENTICATION_INDICATOR = 1;

    protected static PaymentProduct $paymentConfig;

    protected static function loadPaymentConfig(): PaymentProduct
    {
        return new PaymentProduct([
            'productCode' => 'cb,visa,mastercard,maestro',
            'additionalFields' => true,
            'canManualCapturePartially' => true,
            'canRefundPartially' => true,
        ]);
    }

    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Google Pay',
            'de-DE' => 'Google Pay',
        ];

        return $names[$lang] ?? null;
    }

    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order with Google Pay',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung mit Google Pay',
        ];

        return $descriptions[$lang] ?? null;
    }

    protected static function forceHostedFields(): bool
    {
        return true;
    }

    public static function addDefaultCustomFields(): array
    {
        return [
            'displayName' => '',
        ];
    }

    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, OrderTransactionEntity $orderTransaction): OrderRequest
    {
        $paymentMethod = new CardTokenPaymentMethod();
        $paymentMethod->cardtoken = $payload['token'];
        $paymentMethod->eci = 7;
        $paymentMethod->authentication_indicator = self::AUTHENTICATION_INDICATOR;

        $orderRequest->paymentMethod = $paymentMethod;
        $orderRequest->payment_product = $payload['payment_product'];

        return $orderRequest;
    }
}
