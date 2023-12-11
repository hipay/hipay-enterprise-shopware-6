<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\CardTokenPaymentMethod;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;

/**
 * ApplePay payment Methods.
 */
class ApplePay extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 5;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'applepay.svg';

    /** {@inheritDoc} */
    protected static PaymentProduct $paymentConfig;

    /**
     * {@inheritDoc}
     */
    protected static function loadPaymentConfig(): PaymentProduct
    {
        return new PaymentProduct([
            'productCode' => 'cb,visa,mastercard,american-express,bcmc,maestro',
            'additionalFields' => true,
            'canManualCapturePartially' => true,
            'canRefundPartially' => true,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Apple Pay',
            'de-DE' => 'Apple Pay',
        ];

        return $names[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order with Apple Pay',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung mit der Apple Pay',
        ];

        return $descriptions[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function addDefaultCustomFields(): array
    {
        return [
            'buttonType' => 'default',
            'buttonStyle' => 'black',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, AsyncPaymentTransactionStruct $transaction): OrderRequest
    {
        $paymentMethod = new CardTokenPaymentMethod();
        $paymentMethod->cardtoken = $payload['token'];
        $paymentMethod->eci = 7;
        $paymentMethod->authentication_indicator = $this->config->get3DSAuthenticator();

        $orderRequest->paymentMethod = $paymentMethod;
        $orderRequest->payment_product = $payload['payment_product'];

        $orderRequest->custom_data += ['isApplePay' => 1];

        return $orderRequest;
    }
}
