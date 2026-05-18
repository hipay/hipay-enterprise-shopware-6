<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Enum\Transaction\TransactionState;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\Request;

/**
 * Mybank payment Methods.
 */
class Mybank extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'mybank';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 90;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'mybank.svg';

    protected static PaymentProduct $paymentConfig;

    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'MyBank',
            'de-DE' => 'MyBank',
        ];

        return $names[$lang] ?? null;
    }

    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order by bank transfert with MyBank.',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit MyBank.',
        ];

        return $descriptions[$lang] ?? null;
    }

    public static function getCurrencies(): ?array
    {
        return ['EUR'];
    }

    public static function getCountries(): ?array
    {
        return ['IT'];
    }

    public function finalize(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context
    ): void {
        $orderTransactionId = $transaction->getOrderTransactionId();

        if ($return = $request->query->getAlpha('return')) {
            throw PaymentException::asyncFinalizeInterrupted($orderTransactionId, 'Payment ' . $return);
        }

        // Fix the issue that MyBank redirects cancellations to the same accept_url as successful payments.
        $criteria = (new Criteria([$orderTransactionId]))->addAssociation('order');
        /** @var OrderTransactionEntity|null $orderTransaction */
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        $hipayOrderId = $orderTransaction?->getOrder()?->getOrderNumber()
            . '-' . dechex(crc32($orderTransactionId));

        $transactions = $this->clientService
            ->getConfiguredClient()
            ->requestOrderTransactionInformation($hipayOrderId);

        $latestTransaction = !empty($transactions) ? end($transactions) : null;
        $state = $latestTransaction?->getState();

        if (!in_array($state, [TransactionState::COMPLETED, TransactionState::PENDING], true)) {
            throw PaymentException::asyncFinalizeInterrupted($orderTransactionId, 'MyBank payment was cancelled');
        }

        $this->transactionStateHandler->process($orderTransactionId, $context);
    }
}
