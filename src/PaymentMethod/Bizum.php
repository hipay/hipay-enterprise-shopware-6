<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\PhonePaymentMethod;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

/**
 * Bizum payment Methods.
 */
class Bizum extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'bizum';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 75;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'bizum.svg';

    protected static PaymentProduct $paymentConfig;

    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Bizum',
            'de-DE' => 'Bizum',
            'es-ES' => 'Bizum',
        ];

        return $names[$lang] ?? null;
    }

    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order with the Bizum application',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung mit der Bizum Anwendung',
            'es-ES' => 'Paga tu pedido con la aplicación Bizum',
        ];

        return $descriptions[$lang] ?? null;
    }

    public static function getCurrencies(): ?array
    {
        return ['EUR'];
    }

    public static function getCountries(): ?array
    {
        return ['ES'];
    }

    public static function getMinAmount(): ?float
    {
        return 0.5;
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
            $orderRequest->customerBillingInfo->country ?? 'ES'
        );
        $orderRequest->paymentMethod = $paymentMethod;

        $orderRequest->customerBillingInfo->phone = $paymentMethod->phone;

        return $orderRequest;
    }

    protected function hydrateHostedPage(HostedPaymentPageRequest $orderRequest, OrderTransactionEntity $orderTransaction): HostedPaymentPageRequest
    {
        $orderRequest->customerBillingInfo->phone = $this->formatPhoneNumber(
            $orderRequest->customerBillingInfo->phone,
            $orderRequest->customerBillingInfo->country ?? 'ES'
        );

        return $orderRequest;
    }
}
