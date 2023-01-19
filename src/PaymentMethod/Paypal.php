<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Paypal payment Methods.
 */
class Paypal extends AbstractPaymentMethod
{
    public const PAYMENT_NAME = 'paypal';

    public static bool $haveHostedFields = false;

    /** {@inheritDoc} */
    public static function getPosition(): int
    {
        return 20;
    }

    /** {@inheritDoc} */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Paypal',
            'de-DE' => 'Paypal',
        ];

        return $names[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'PayPal is an American company offering an online payment service system worldwide',
            'de-DE' => 'PayPal ist ein amerikanisches Unternehmen, das weltweit ein Online-Zahlungsdienstsystem anbietet',
        ];

        return $descriptions[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getImage(): ?string
    {
        return 'paypal.svg';
    }

    /** {@inheritDoc} */
    public static function getRule(ContainerInterface $container): ?array
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload): OrderRequest
    {
        $orderRequest->payment_product = static::PAYMENT_NAME;

        return $orderRequest;
    }

    /**
     * {@inheritDoc}
     */
    protected function hydrateHostedPage(HostedPaymentPageRequest $orderRequest, AsyncPaymentTransactionStruct $transaction): HostedPaymentPageRequest
    {
        $orderRequest->payment_product_list = static::PAYMENT_NAME;

        return $orderRequest;
    }
}
