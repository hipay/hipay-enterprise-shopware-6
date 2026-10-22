<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\PhonePaymentMethod;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

/**
 * Bancomat Pay payment Methods.
 */
class BancomatPay extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'bancomatpay';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 80;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'bancomatpay.svg';

    protected static PaymentProduct $paymentConfig;

    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Bancomat Pay',
            'de-DE' => 'Bancomat Pay',
            'it-IT' => 'Bancomat Pay',
        ];

        return $names[$lang] ?? null;
    }

    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order with the Bancomat Pay application',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung mit der Bancomat Pay Anwendung',
            'it-IT' => "Paga il tuo ordine con l'applicazione Bancomat Pay",
        ];

        return $descriptions[$lang] ?? null;
    }

    public static function getCurrencies(): ?array
    {
        return ['EUR'];
    }

    public static function getCountries(): ?array
    {
        return ['IT', 'VA', 'SM'];
    }

    protected static function forceCaptureAuto(): bool
    {
        return true;
    }

    protected static function usesPendingPage(): bool
    {
        return true;
    }

    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, OrderTransactionEntity $orderTransaction): OrderRequest
    {
        $paymentMethod = new PhonePaymentMethod();
        $paymentMethod->phone = $this->formatPhoneNumber(
            $payload['phone'],
            $orderRequest->customerBillingInfo->country ?? 'IT'
        );
        $orderRequest->paymentMethod = $paymentMethod;

        $orderRequest->customerBillingInfo->phone = $paymentMethod->phone;

        return $orderRequest;
    }

    protected function hydrateHostedPage(HostedPaymentPageRequest $orderRequest, OrderTransactionEntity $orderTransaction): HostedPaymentPageRequest
    {
        $orderRequest->customerBillingInfo->phone = $this->formatPhoneNumber(
            $orderRequest->customerBillingInfo->phone,
            $orderRequest->customerBillingInfo->country ?? 'IT'
        );

        return $orderRequest;
    }
}
