<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\SEPADirectDebitPaymentMethod;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;

/**
 * SepaDirectDebit payment Methods.
 */
class SepaDirectDebit extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'sdd';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 30;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'sepa-direct-debit.svg';

    /** {@inheritDoc} */
    protected static PaymentProduct $paymentConfig;

    /**
     * {@inheritDoc}
     */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'SEPA Direct Debit',
            'de-DE' => 'SEPA Direct Debit',
        ];

        return $names[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'We\'ll automatically debit the amount from your bank account.',
            'de-DE' => 'Wir werden den Betrag automatisch von Ihrem Bankkonto abbuchen.',
        ];

        return $descriptions[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function getCurrencies(): ?array
    {
        return ['EUR'];
    }

    /**
     * {@inheritDoc}
     */
    public static function getCountries(): ?array
    {
        return ['BE', 'FR', 'GP', 'GF', 'IT', 'RE', 'MA', 'MC', 'PT', 'MQ', 'YT', 'NC', 'SP', 'CH'];
    }

    /**
     * {@inheritDoc}
     */
    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, AsyncPaymentTransactionStruct $transaction): OrderRequest
    {
        $paymentMethod = new SEPADirectDebitPaymentMethod();

        $paymentMethod->firstname = $payload['firstname'];
        $paymentMethod->lastname = $payload['lastname'];
        $paymentMethod->iban = $payload['iban'];
        $paymentMethod->gender = $payload['gender'];
        $paymentMethod->bank_name = $payload['bank_name'];
        $paymentMethod->recurring_payment = 0;

        $orderRequest->paymentMethod = $paymentMethod;

        return $orderRequest;
    }
}
