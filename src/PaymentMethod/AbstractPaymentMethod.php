<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Enum\ThreeDSTwo\DeliveryTimeFrame;
use HiPay\Fullservice\Enum\ThreeDSTwo\DeviceChannel;
use HiPay\Fullservice\Enum\ThreeDSTwo\PurchaseIndicator;
use HiPay\Fullservice\Enum\ThreeDSTwo\ReorderIndicator;
use HiPay\Fullservice\Enum\ThreeDSTwo\ShippingIndicator;
use HiPay\Fullservice\Exception\UnexpectedValueException;
use HiPay\Fullservice\Gateway\Model\Request\ThreeDSTwo\AccountInfo;
use HiPay\Fullservice\Gateway\Model\Request\ThreeDSTwo\AccountInfo\Customer;
use HiPay\Fullservice\Gateway\Model\Request\ThreeDSTwo\AccountInfo\Payment;
use HiPay\Fullservice\Gateway\Model\Request\ThreeDSTwo\AccountInfo\Purchase;
use HiPay\Fullservice\Gateway\Model\Request\ThreeDSTwo\AccountInfo\Shipping;
use HiPay\Fullservice\Gateway\Model\Request\ThreeDSTwo\MerchantRiskStatement;
use HiPay\Fullservice\Gateway\Model\Request\ThreeDSTwo\MerchantRiskStatement\GiftCard;
use HiPay\Fullservice\Gateway\Request\Info\CustomerBillingInfoRequest;
use HiPay\Fullservice\Gateway\Request\Info\CustomerShippingInfoRequest;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Payment\Service\HiPayHttpClientService;
use HiPay\Payment\Service\ReadHipayConfigService;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Abstract class for HiPay payment mathods.
 */
abstract class AbstractPaymentMethod implements AsynchronousPaymentHandlerInterface, PaymentMethodInterface
{
    protected OrderTransactionStateHandler $transactionStateHandler;

    protected HiPayHttpClientService $clientService;

    protected ReadHipayConfigService $config;

    protected ?Request $request;

    protected LocaleProvider $localeProvider;

    private EntityRepository $orderCustomerRepo;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ReadHipayConfigService $config,
        HiPayHttpClientService $clientService,
        RequestStack $requestStack,
        LocaleProvider $localeProvider,
        EntityRepository $orderCustomerRepository
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->config = $config;
        $this->clientService = $clientService;
        $this->request = $requestStack->getCurrentRequest();
        $this->localeProvider = $localeProvider;
        $this->orderCustomerRepo = $orderCustomerRepository;
    }

    /**
     * {@inheritDoc}
     */
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        try {
            $locale = $this->localeProvider->getLocaleFromContext($salesChannelContext->getContext());
            $redirectUri = $this->getRedirectUri($transaction, $locale);
        } catch (\Exception $e) {
            throw new AsyncPaymentProcessException($transaction->getOrderTransaction()->getId(), 'An error occurred during the communication with external payment gateway'.PHP_EOL.$e->getMessage());
        }

        // Redirect to external gateway
        return new RedirectResponse($redirectUri);
    }

    /**
     * {@inheritDoc}
     */
    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
    }

    /**
     * Generate the redirect URI after payment.
     */
    private function getRedirectUri(AsyncPaymentTransactionStruct $transaction, string $locale): string
    {
        $client = $this->clientService->getConfiguredClient();

        if ($this->config->isHostedFields()) {
            // hosted fields
            $response = $client->requestNewOrder(
                $this->generateRequestHostedFields($transaction, $locale)
            );

            return $response->getForwardUrl() ?: $transaction->getReturnUrl();
        }

        if ($this->config->isHostedPage()) {
            // hosted page
            $response = $client->requestHostedPaymentPage(
                $this->generateRequestHostedPage($transaction, $locale)
            );

            return $response->getForwardUrl();
        }

        throw new UnexpectedValueException('Configuration mode "'.$this->config->getOperationMode().'" is invalid');
    }

    /**
     * Generate the request on hosted page mode.
     */
    private function generateRequestHostedPage(AsyncPaymentTransactionStruct $transaction, string $locale): HostedPaymentPageRequest
    {
        return $this->hydrateHostedPage(
            // @phpstan-ignore-next-line
            $this->hydrateGenericOrderRequest(new HostedPaymentPageRequest(), $transaction, $locale),
            $transaction
        );
    }

    /**
     * Generate the request on hosted fields mode.
     */
    private function generateRequestHostedFields(AsyncPaymentTransactionStruct $transaction, string $locale): OrderRequest
    {
        return $this->hydrateHostedFields(
            $this->hydrateGenericOrderRequest(new OrderRequest(), $transaction, $locale)
        );
    }

    /**
     * hydrate the generic orderRequest.
     */
    private function hydrateGenericOrderRequest(OrderRequest $orderRequest, AsyncPaymentTransactionStruct $transaction, string $locale): OrderRequest
    {
        $order = $transaction->getOrder();

        // Order data
        $orderRequest->orderid = $transaction->getOrderTransaction()->getId();
        $orderRequest->operation = $this->config->isCaptureAuto() ? 'Sale' : 'Authorization';
        $orderRequest->description = $this->generateDescription($order->getLineItems(), 255, '...');
        // $orderRequest->basket = $this->generateBasket($transaction->getOrder());

        // Amounts data
        if ($order->getCurrency()) {
            $orderRequest->currency = $order->getCurrency()->getIsoCode();
        }
        $orderRequest->shipping = $order->getShippingTotal();
        $orderRequest->amount = $order->getAmountTotal();
        $orderRequest->tax = $order->getAmountTotal() - $order->getAmountNet();

        // Client Data
        $orderRequest->language = str_replace('-', '_', $locale);
        $orderRequest->ipaddr = $order->getOrderCustomer()->getRemoteAddress();
        $orderRequest->http_user_agent = $this->request->headers->get('User-Agent');
        $orderRequest->http_accept = 'application/json';
        $orderRequest->device_channel = DeviceChannel::BROWSER;

        // Customer data
        $orderRequest->cid = $order->getOrderCustomer()->getId();
        $orderRequest->customerBillingInfo = $this->generateCustomerBillingInfo($order);
        $orderRequest->customerShippingInfo = $this->generateCustomerShippingInfo($order);
        $orderRequest->custom_data = [
            'operation_id' => Uuid::uuid4()->toString(),
        ];

        $orderRequest->merchant_risk_statement = $this->generateMerchantRiskStatement($order);
        $orderRequest->account_info = $this->generateAccountInfo($order);

        // TODO: redirect url
        $orderRequest->accept_url = $transaction->getReturnUrl();
        $orderRequest->decline_url = $transaction->getReturnUrl();
        $orderRequest->pending_url = $transaction->getReturnUrl();
        $orderRequest->exception_url = $transaction->getReturnUrl();
        $orderRequest->cancel_url = $transaction->getReturnUrl();
        $orderRequest->notify_url = $this->request->getSchemeAndHttpHost().'/api/hipay/notify';

        return $orderRequest;
    }

    /**
     * Generate a description.
     */
    private function generateDescription(OrderLineItemCollection $lineItems, int $maxLength = 255, string $trailing = ''): string
    {
        $description = [];
        foreach ($lineItems as $lineItem) {
            if ('product' === $lineItem->getType()) {
                $description[] = $lineItem->getQuantity().' x '.$lineItem->getLabel();
            }
        }
        $description = implode(' + ', $description);
        $maxLength -= strlen($trailing);

        return strlen($description) <= $maxLength ? $description : substr($description, 0, $maxLength).$trailing;
    }

    /*
    private function generateBasket(OrderEntity $order): array
    {
        $listType = [
            'promotion' => 'discount',
        ];

        $basket = [];
        foreach ($order->getLineItems() as $lineItem) {
            $basket[] = [
                'product_reference' => $lineItem->getPayload()['productNumber'],
                'name' => $lineItem->getLabel(),
                'type' => $listType[$lineItem->getType()] ?? 'good',
                'quantity' => $lineItem->getQuantity(),
                'unit_price' => $lineItem->getUnitPrice(),
                'discount' => 0,
                // 'tax_rate' => $lineItem->getPrice()->getCalculatedTaxes(),
                'total_amount' => $lineItem->getTotalPrice(),
            ];
        }

        return $basket;
    }
    */

    /**
     * Retreive billing informations.
     */
    private function generateCustomerBillingInfo(OrderEntity $order): CustomerBillingInfoRequest
    {
        $billingAddress = $order->getBillingAddress();

        $billingInfo = new CustomerBillingInfoRequest();
        // Identity
        $billingInfo->firstname = $billingAddress->getFirstName();
        $billingInfo->lastname = $billingAddress->getLastName();
        $billingInfo->email = $order->getOrderCustomer()->getEmail();
        $billingInfo->phone = $billingAddress->getPhoneNumber();
        $billingInfo->gender = $this->generateGender($billingAddress->getSalutation());

        // Postal data
        $billingInfo->recipientinfo = $billingAddress->getCompany();
        $billingInfo->streetaddress = $billingAddress->getStreet();
        $billingInfo->streetaddress2 = trim(
            trim($billingAddress->getAdditionalAddressLine1()).' '.trim($billingAddress->getAdditionalAddressLine2())
        );
        $billingInfo->zipcode = $billingAddress->getZipcode();
        $billingInfo->city = $billingAddress->getCity();
        $billingInfo->country = $billingAddress->getCountry()->getIso();

        $billingInfo->state = $this->generateState(
            $billingInfo->country,
            $billingAddress->getCountryState()
        );

        return $billingInfo;
    }

    /**
     * Retreive shipping informations.
     */
    private function generateCustomerShippingInfo(OrderEntity $order): CustomerShippingInfoRequest
    {
        $shippingInfo = new CustomerShippingInfoRequest();

        if ($shippingAddress = $order->getDeliveries()->getShippingAddress()->first()) {
            // Identity
            $shippingInfo->shipto_firstname = $shippingAddress->getFirstName();
            $shippingInfo->shipto_lastname = $shippingAddress->getLastName();
            $shippingInfo->shipto_gender = $this->generateGender($shippingAddress->getSalutation());
            $shippingInfo->shipto_phone = $shippingAddress->getPhoneNumber();

            // Postal data
            $shippingInfo->shipto_recipientinfo = $shippingAddress->getCompany();
            $shippingInfo->shipto_streetaddress = $shippingAddress->getStreet();
            $shippingInfo->shipto_streetaddress2 = trim(
                trim($shippingAddress->getAdditionalAddressLine1()).' '.trim($shippingAddress->getAdditionalAddressLine2())
            );
            $shippingInfo->shipto_city = $shippingAddress->getCity();
            $shippingInfo->shipto_zipcode = $shippingAddress->getZipcode();
            $shippingInfo->shipto_country = $shippingAddress->getCountry()->getIso();
            $shippingInfo->shipto_state = $this->generateState(
                $shippingInfo->shipto_country,
                $shippingAddress->getCountryState()
            );
        }

        return $shippingInfo;
    }

    /**
     * Generate Merchant risk statement.
     */
    private function generateMerchantRiskStatement(OrderEntity $order): MerchantRiskStatement
    {
        $statement = new MerchantRiskStatement();

        // Delivery time frame
        if ($shippingAddress = $order->getDeliveries()->first()) {
            $deliveryDelay = $shippingAddress->getShippingDateEarliest()->diff(new \DateTime());

            $statement->delivery_time_frame = DeliveryTimeFrame::TWO_DAY_OR_MORE_SHIPPING;
            if ($deliveryDelay->days < 1) {
                $statement->delivery_time_frame = DeliveryTimeFrame::OVERNIGHT_SHIPPING;
            } elseif ($deliveryDelay->days < 2) {
                $statement->delivery_time_frame = DeliveryTimeFrame::SAME_DAY_SHIPPING;
            }
        }

        // Purchase Indicator
        $statement->purchase_indicator = PurchaseIndicator::FUTURE_AVAILABILITY;
        foreach ($order->getLineItems() as $lineItem) {
            if (($payload = $lineItem->getPayload()) && $payload['stock'] > 0) {
                $statement->purchase_indicator = PurchaseIndicator::MERCHANDISE_AVAILABLE;
                break;
            }
        }

        // Reorder indicator
        $statement->reorder_indicator = ReorderIndicator::FIRST_TIME_ORDERED;

        $orderCustomers = $this->getOrderCustomers($order->getOrderCustomer()->getCustomer()->getId());

        $sameOrders = $orderCustomers->filter(
            function (OrderCustomerEntity $orderCustomer) use ($order) {
                $mapLineItemsCallback = function (OrderLineItemCollection $lineItems) {
                    /* @infection-ignore-all */
                    $lineItemsHashs = $lineItems->map(
                        fn (OrderLineItemEntity $lineItem) => $lineItem->getQuantity().$lineItem->getProductId()
                    );
                    sort($lineItemsHashs);

                    return $lineItemsHashs;
                };

                return $order->getId() !== $orderCustomer->getOrderId()
                && $mapLineItemsCallback($order->getLineItems()) === $mapLineItemsCallback($orderCustomer->getOrder()->getLineItems());
            }
        );

        if (count($sameOrders)) {
            $statement->reorder_indicator = ReorderIndicator::REORDERED;
        }

        // Shipping indicator
        $statement->shipping_indicator = $this->generateShippingIndicator($order);

        // Gift card
        $statement->gift_card = new GiftCard();

        return $statement;
    }

    /**
     * Generate Account Info.
     */
    private function generateAccountInfo(OrderEntity $order): AccountInfo
    {
        $customer = $order->getOrderCustomer()->getCustomer();
        $accountInfo = new AccountInfo();

        $accountInfo->customer = new Customer();

        $accountChange = ($customer->getUpdatedAt() ?? $customer->getCreatedAt());
        $accountInfo->customer->account_change = (int) $accountChange->format('Ymd');

        $accountInfo->customer->opening_account_date = (int) $customer->getCreatedAt()->format('Ymd');

        $accountInfo->purchase = new Purchase();
        $accountInfo->purchase->count = count(
            $this->getOrderCustomers($customer->getId())->filter(
                fn (OrderCustomerEntity $oc) => $oc->getOrderId() !== $order->getId()
                    && $oc->getOrder()->getCreatedAt() >= (new \DateTime())->modify('-6 months')
            )
        );

        $countPaymentAttemptFn = fn (OrderCustomerCollection $occ, $delay) => (int) array_sum(
            $occ->map(
                fn (OrderCustomerEntity $oc) => count(
                    $oc->getOrder()->getTransactions()->filter(
                        fn (OrderTransactionEntity $ot) => $ot->getOrderId() !== $order->getId()
                            && CreditCard::class === $ot->getPaymentMethod()->getHandlerIdentifier()
                            && $ot->getCreatedAt() >= (new \DateTime())->modify($delay)
                    )
                )
            )
        );

        $orderCustomers = $this->getOrderCustomers($customer->getId());

        $accountInfo->purchase->payment_attempts_24h = $countPaymentAttemptFn($orderCustomers, '-1 day');
        $accountInfo->purchase->payment_attempts_1y = $countPaymentAttemptFn($orderCustomers, '-1 year');

        $accountInfo->shipping = new Shipping();

        if ($shippingAddress = $order->getDeliveries()->getShippingAddress()->first()) {
            $hashAddress = $this->getAddressHash($shippingAddress);
            foreach ($orderCustomers as $oc) {
                if ($oc->getOrderId() !== $order->getId() && $hashAddress === $this->getAddressHash($shippingAddress)) {
                    $accountInfo->shipping->shipping_used_date = (int) $oc->getOrder()->getCreatedAt()->format('Ymd');
                    break;
                }
            }
        }

        return $accountInfo;
    }

    /**
     * Convert Salutation into gender.
     */
    private function generateGender(?SalutationEntity $salutation): string
    {
        $gender = 'U';

        if ($salutation) {
            if ('mrs' === $salutation->getSalutationKey()) {
                $gender = 'F';
            } elseif ('mr' === $salutation->getSalutationKey()) {
                $gender = 'M';
            }
        }

        return $gender;
    }

    /**
     * Generate the State field.
     */
    private function generateState(string $country, ?CountryStateEntity $countryState): ?string
    {
        $state = null;

        if (in_array($country, ['US', 'CA']) && $countryState) {
            $state = $countryState->getName();
        }

        return $state;
    }

    /**
     * Determinate the shipping indicator.
     */
    private function generateShippingIndicator(OrderEntity $order): int
    {
        if ($shippingAddress = $order->getDeliveries()->getShippingAddress()->first()) {
            $shippingHash = $this->getAddressHash($shippingAddress);

            if ($this->getAddressHash($order->getBillingAddress()) === $shippingHash) {
                return ShippingIndicator::SHIP_TO_CARDHOLDER_BILLING_ADDRESS;
            }

            if ($customer = $order->getOrderCustomer()->getCustomer()) {
                foreach ($this->getOrderCustomers($customer->getId()) as $orderCustomer) {
                    $shipping = $orderCustomer->getOrder()->getDeliveries()->getShippingAddress()->first();
                    if (
                        $shipping
                        && $orderCustomer->getOrderId() !== $order->getId()
                        && $shippingHash == $this->getAddressHash($shipping)
                    ) {
                        return ShippingIndicator::SHIP_TO_VERIFIED_ADDRESS;
                    }
                }
            }
        }

        return ShippingIndicator::SHIP_TO_DIFFERENT_ADDRESS;
    }

    /**
     * Generate an hash for an address.
     *
     * @infection-ignore-all
     */
    private function getAddressHash(OrderAddressEntity $address): string
    {
        return $address->getSalutationId()
        .$address->getFirstName()
        .$address->getLastName()
        .$address->getStreet()
        .$address->getZipcode()
        .$address->getCity()
        .$address->getCompany()
        .$address->getTitle()
        .$address->getPhoneNumber()
        .$address->getAdditionalAddressLine1()
        .$address->getAdditionalAddressLine2()
        .$address->getCountryId()
        .$address->getCountryStateId();
    }

    /**
     * Get the OrderCustomers from a customer Id.
     */
    private function getOrderCustomers(string $customerId): OrderCustomerCollection
    {
        return new OrderCustomerCollection(
            // @phpstan-ignore-next-line
            $this->orderCustomerRepo->search(
                (new Criteria())
                    ->addFilter(new EqualsFilter('customerId', $customerId))
                    ->addAssociation('order')
                    ->addAssociation('order.deliveries')
                    ->addAssociation('order.transactions')
                    ->addAssociation('order.transactions.paymentMethod')
                    ->addAssociation('order.lineItems')
                    ->addSorting(new FieldSorting('order.createdAt', FieldSorting::DESCENDING))
                    ->setLimit(5),
                Context::createDefaultContext()
            )->getEntities()
        );
    }

    /**
     * {@inheritDoc}
     */
    public static function addDefaultCustomFields(): array
    {
        return [];
    }

    /**
     * Configure hosted fields request for the current payment method.
     */
    abstract protected function hydrateHostedFields(OrderRequest $orderRequest): OrderRequest;

    /**
     * Configure hosted page request for the current payment method.
     */
    abstract protected function hydrateHostedPage(HostedPaymentPageRequest $orderRequest, AsyncPaymentTransactionStruct $transaction): HostedPaymentPageRequest;
}
