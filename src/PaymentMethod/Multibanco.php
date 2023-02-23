<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Enum\Transaction\TransactionState;
use HiPay\Fullservice\Gateway\Model\Transaction;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\ExpirationLimitPaymentMethod;
use HiPay\Payment\Logger\HipayLogger;
use HiPay\Payment\Service\HiPayHttpClientService;
use HiPay\Payment\Service\ReadHipayConfigService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Multibanco payment Methods.
 */
class Multibanco extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'multibanco';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 80;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'multibanco.svg';

    /** {@inheritDoc} */
    protected static PaymentProduct $paymentConfig;

    protected EntityRepository $transactionRepo;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ReadHipayConfigService $config,
        HiPayHttpClientService $clientService,
        RequestStack $requestStack,
        LocaleProvider $localeProvider,
        EntityRepository $orderCustomerRepository,
        HipayLogger $hipayLogger,
        EntityRepository $orderTransactionRepository
    ) {
        parent::__construct(
            $transactionStateHandler,
            $config,
            $clientService,
            $requestStack,
            $localeProvider,
            $orderCustomerRepository,
            $hipayLogger
        );

        $this->transactionRepo = $orderTransactionRepository;
    }

    /**
     * {@inheritDoc}
     */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Multibanco',
            'de-DE' => 'Multibanco',
        ];

        return $names[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order with the Multibanco',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung mit der Multibanco',
        ];

        return $descriptions[$lang] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public static function addDefaultCustomFields(): array
    {
        return ['expiration_limit' => '3'];
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
        return ['PT'];
    }

    /**
     * {@inheritDoc}
     */
    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, AsyncPaymentTransactionStruct $transaction): OrderRequest
    {
        $customFields = $transaction->getOrderTransaction()->getPaymentMethod()->getCustomFields();

        $paymentMethod = new ExpirationLimitPaymentMethod();
        $paymentMethod->expiration_limit = intval($customFields['expiration_limit'] ?? 3);
        // @phpstan-ignore-next-line
        $orderRequest->paymentMethod = $paymentMethod;

        return $orderRequest;
    }

    /**
     * {@inheritDoc}
     */
    protected function handleHostedFieldResponse(AsyncPaymentTransactionStruct $transaction, Transaction $response): string
    {
        // error as main return
        $redirect = $transaction->getReturnUrl().'&return='.TransactionState::ERROR;

        switch ($response->getState()) {
            case TransactionState::FORWARDING:
            case TransactionState::COMPLETED:
            case TransactionState::PENDING:
                $redirect = $transaction->getReturnUrl();
                break;

            case TransactionState::DECLINED:
                $redirect = $transaction->getReturnUrl().'&return='.TransactionState::DECLINED;
                break;
        }

        // save the reference to pay
        $this->transactionRepo->update(
            [[
                'id' => $transaction->getOrderTransaction()->getId(),
                'customFields' => array_merge(
                    $transaction->getOrderTransaction()->getCustomFields() ?? [],
                    ['reference_to_pay' => $response->getReferenceToPay()]
                ),
            ]],
            Context::createDefaultContext()
        );

        return $redirect;
    }

    /**
     * {@inheritDoc}
     */
    protected function hydrateHostedPage(HostedPaymentPageRequest $orderRequest, AsyncPaymentTransactionStruct $transaction): HostedPaymentPageRequest
    {
        $customFields = $transaction->getOrderTransaction()->getPaymentMethod()->getCustomFields();

        $paymentMethod = new ExpirationLimitPaymentMethod();
        $paymentMethod->expiration_limit = intval($customFields['expiration_limit'] ?? 3);
        // @phpstan-ignore-next-line
        $orderRequest->paymentMethod = $paymentMethod;

        return $orderRequest;
    }
}
