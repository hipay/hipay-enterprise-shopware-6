<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Enum\Transaction\TransactionState;
use HiPay\Fullservice\Gateway\Model\Transaction;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\ExpirationLimitPaymentMethod;
use HiPay\Payment\Service\HiPayHttpClientService;
use HiPay\Payment\Service\ReadHipayConfigService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
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

    protected static PaymentProduct $paymentConfig;

    /**
     * @param EntityRepository<OrderCustomerCollection> $orderCustomerRepository
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     */
    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ReadHipayConfigService       $config,
        HiPayHttpClientService       $clientService,
        RequestStack                 $requestStack,
        LocaleProvider               $localeProvider,
        EntityRepository             $orderCustomerRepository,
        LoggerInterface              $logger,
        protected EntityRepository   $orderTransactionRepository
    )
    {
        parent::__construct(
            $transactionStateHandler,
            $config,
            $clientService,
            $requestStack,
            $localeProvider,
            $orderCustomerRepository,
            $logger
        );
    }

    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Multibanco',
            'de-DE' => 'Multibanco',
        ];

        return $names[$lang] ?? null;
    }

    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order with the Multibanco',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung mit der Multibanco',
        ];

        return $descriptions[$lang] ?? null;
    }

    public static function addDefaultCustomFields(): array
    {
        return ['expiration_limit' => '3'];
    }

    public static function getCurrencies(): ?array
    {
        return ['EUR'];
    }

    public static function getCountries(): ?array
    {
        return ['PT'];
    }

    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, AsyncPaymentTransactionStruct $transaction): OrderRequest
    {
        $customFields = $transaction->getOrderTransaction()->getPaymentMethod()->getCustomFields();

        $paymentMethod = new ExpirationLimitPaymentMethod();
        $paymentMethod->expiration_limit = intval($customFields['expiration_limit'] ?? 3);
        $orderRequest->paymentMethod = $paymentMethod;

        return $orderRequest;
    }

    protected function handleHostedFieldResponse(AsyncPaymentTransactionStruct $transaction, Transaction $response, Context $context): string
    {
        // error as main return
        $redirect = $transaction->getReturnUrl() . '&return=' . TransactionState::ERROR;

        switch ($response->getState()) {
            case TransactionState::FORWARDING:
            case TransactionState::COMPLETED:
            case TransactionState::PENDING:
                $redirect = $transaction->getReturnUrl();
                break;

            case TransactionState::DECLINED:
                $redirect = $transaction->getReturnUrl() . '&return=' . TransactionState::DECLINED;
                break;
        }

        // save the reference to pay
        $this->orderTransactionRepository->update(
            [[
                'id' => $transaction->getOrderTransaction()->getId(),
                'customFields' => array_merge(
                    $transaction->getOrderTransaction()->getCustomFields() ?? [],
                    ['reference_to_pay' => $response->getReferenceToPay ? json_decode($response->getReferenceToPay()) : []]
                ),
            ]],
            $context
        );

        return $redirect;
    }

    protected function hydrateHostedPage(HostedPaymentPageRequest $orderRequest, AsyncPaymentTransactionStruct $transaction): HostedPaymentPageRequest
    {
        $customFields = $transaction->getOrderTransaction()->getPaymentMethod()->getCustomFields();

        $paymentMethod = new ExpirationLimitPaymentMethod();
        $paymentMethod->expiration_limit = intval($customFields['expiration_limit'] ?? 3);
        $orderRequest->paymentMethod = $paymentMethod;

        return $orderRequest;
    }
}
