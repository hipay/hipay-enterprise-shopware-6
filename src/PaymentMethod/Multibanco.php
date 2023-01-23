<?php

namespace HiPay\Payment\PaymentMethod;

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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Multibanco payment Methods.
 */
class Multibanco extends AbstractPaymentMethod
{
    protected EntityRepository $transactionRepo;

    public const PAYMENT_NAME = 'multibanco';

    public static bool $haveHostedFields = false;

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

    /** {@inheritDoc} */
    public static function getPosition(): int
    {
        return 80;
    }

    /** {@inheritDoc} */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Multibanco',
            'de-DE' => 'Multibanco',
        ];

        return $names[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Pay your order with the Multibanco',
            'de-DE' => 'Bezahlen Sie Ihre Bestellung mit der Multibanco',
        ];

        return $descriptions[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getImage(): ?string
    {
        return 'multibanco.svg';
    }

    /** {@inheritDoc} */
    public static function addDefaultCustomFields(): array
    {
        return parent::addDefaultCustomFields() + [
            'expiration_limit' => '3',
        ];
    }

    /** {@inheritDoc} */
    public static function getRule(ContainerInterface $container): ?array
    {
        /** @var EntityRepository */
        $currencyRepo = $container->get('currency.repository');
        $currencyId = $currencyRepo->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('isoCode', 'EUR')),
            Context::createDefaultContext()
        )->firstId();

        /** @var EntityRepository */
        $countryRepo = $container->get('country.repository');
        $countryId = $countryRepo->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('iso', 'PT')),
            Context::createDefaultContext()
        )->firstId();

        return [
            'name' => 'Multibanco rule (only EUR from Portugal)',
            'description' => 'Specific rule for Multibanco : currency in Euro for Portugal only',
            'priority' => 1,
            'conditions' => [
                [
                    'id' => $andId = Uuid::randomHex(),
                    'type' => 'andContainer',
                    'position' => 0,
                ],
                [
                    'type' => 'currency',
                    'position' => 0,
                    'value' => [
                            'operator' => Rule::OPERATOR_EQ,
                            'currencyIds' => [$currencyId],
                        ],
                    'parentId' => $andId,
                ],
                [
                    'type' => 'customerBillingCountry',
                    'position' => 1,
                    'value' => [
                            'operator' => Rule::OPERATOR_EQ,
                            'countryIds' => [$countryId],
                        ],
                    'parentId' => $andId,
                ],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @throws BadRequestException
     */
    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, AsyncPaymentTransactionStruct $transaction): OrderRequest
    {
        $customFields = $transaction->getOrderTransaction()->getPaymentMethod()->getCustomFields();

        $paymentMethod = new ExpirationLimitPaymentMethod();
        $paymentMethod->expiration_limit = intval($customFields['expiration_limit'] ?? 3);
        // @phpstan-ignore-next-line
        $orderRequest->paymentMethod = $paymentMethod;
        $orderRequest->payment_product = static::PAYMENT_NAME;

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
        $this->transactionRepo->update([[
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

        $orderRequest->payment_product_list = static::PAYMENT_NAME;

        return $orderRequest;
    }
}
