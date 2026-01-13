<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\IssuerBankIDPaymentMethod;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

/**
 * Ideal payment Methods.
 */
class Ideal extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'ideal';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 60;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'ideal.svg';

    protected static PaymentProduct $paymentConfig;

    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Ideal',
            'de-DE' => 'Ideal',
        ];

        return $names[$lang] ?? null;
    }

    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order by bank transfert with iDEAL',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit iDEAL',
        ];

        return $descriptions[$lang] ?? null;
    }

    public static function getCurrencies(): ?array
    {
        return ['EUR'];
    }

    public static function getCountries(): ?array
    {
        return ['NL'];
    }

    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, OrderTransactionEntity $orderTransaction): OrderRequest
    {
        if(isset($payload['issuer_bank_id']) && !empty($payload['issuer_bank_id'])) {
        
            $paymentMethod = new IssuerBankIDPaymentMethod();
            $paymentMethod->issuer_bank_id = $payload['issuer_bank_id'];

            $orderRequest->paymentMethod = $paymentMethod;
        }

        return $orderRequest;
    }
}
