<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\CardTokenPaymentMethod;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;

/**
 * Credit card payment Methods.
 */
class CreditCard extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const TECHNICAL_NAME = 'credit-card';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 10;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'credit_card.svg';

    protected static PaymentProduct $paymentConfig;

    protected static function loadPaymentConfig(): PaymentProduct
    {
        return new PaymentProduct([
            'productCode' => 'cb,visa,mastercard,american-express,bcmc,maestro',
            'additionalFields' => true,
            'canManualCapturePartially' => true,
            'canRefundPartially' => true,
        ]);
    }

    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Credit Cards',
            'de-DE' => 'Kreditkarten',
        ];

        return $names[$lang] ?? null;
    }

    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Use your credit cards to safely pay through our PCI DSS certified payment provider',
            'de-DE' => 'Verwenden Sie Ihre Kreditkarten, um sicher über unseren PCI DSS-zertifizierten Zahlungsanbieter zu bezahlen',
        ];

        return $descriptions[$lang] ?? null;
    }

    public static function addDefaultCustomFields(): array
    {
        return [
            'cards' => ['cb', 'visa', 'mastercard', 'american-express', 'bcmc', 'maestro'],
        ];
    }

    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, AsyncPaymentTransactionStruct $transaction): OrderRequest
    {
        $paymentMethod = new CardTokenPaymentMethod();
        $paymentMethod->cardtoken = $payload['token'];
        $paymentMethod->eci = isset($payload['card_id']) ? 7 : 9;
        $paymentMethod->authentication_indicator = $this->config->get3DSAuthenticator();

        $orderRequest->paymentMethod = $paymentMethod;
        $orderRequest->payment_product = $payload['payment_product'];

        if ($this->request->get('hipay-multiuse')) {
            $orderRequest->custom_data += ['multiuse' => true];
        }

        return $orderRequest;
    }

    protected function hydrateHostedPage(HostedPaymentPageRequest $orderRequest, AsyncPaymentTransactionStruct $transaction): HostedPaymentPageRequest
    {
        $customFields = $transaction->getOrderTransaction()->getPaymentMethod()->getCustomFields();

        $orderRequest->payment_product_list = implode(',', $customFields['cards'] ?? []);

        return $orderRequest;
    }
}
