<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Enum\ThreeDSTwo\DeviceChannel;
use HiPay\Fullservice\Exception\UnexpectedValueException;
use HiPay\Fullservice\Gateway\Request\Info\CustomerBillingInfoRequest;
use HiPay\Fullservice\Gateway\Request\Info\CustomerShippingInfoRequest;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Payment\Service\HiPayHttpClientService;
use HiPay\Payment\Service\ReadHipayConfigService;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
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

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ReadHipayConfigService $config,
        HiPayHttpClientService $clientService,
        RequestStack $requestStack,
        LocaleProvider $localeProvider
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->config = $config;
        $this->clientService = $clientService;
        $this->request = $requestStack->getCurrentRequest();
        $this->localeProvider = $localeProvider;
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
            $client->requestNewOrder(
                $this->generateRequestHostedFields($transaction, $locale)
            );

            return $transaction->getReturnUrl();
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
        $shippingOrder = $order->getDeliveries()->first()->getShippingOrderAddress();

        $shippingInfo = new CustomerShippingInfoRequest();
        // Identity
        $shippingInfo->shipto_firstname = $shippingOrder->getFirstName();
        $shippingInfo->shipto_lastname = $shippingOrder->getLastName();
        $shippingInfo->shipto_gender = $this->generateGender($shippingOrder->getSalutation());
        $shippingInfo->shipto_phone = $shippingOrder->getPhoneNumber();

        // Postal data
        $shippingInfo->shipto_recipientinfo = $shippingOrder->getCompany();
        $shippingInfo->shipto_streetaddress = $shippingOrder->getStreet();
        $shippingInfo->shipto_streetaddress2 = trim(
            trim($shippingOrder->getAdditionalAddressLine1()).' '.trim($shippingOrder->getAdditionalAddressLine2())
        );
        $shippingInfo->shipto_city = $shippingOrder->getCity();
        $shippingInfo->shipto_zipcode = $shippingOrder->getZipcode();
        $shippingInfo->shipto_country = $shippingOrder->getCountry()->getIso();
        $shippingInfo->shipto_state = $this->generateState(
            $shippingInfo->shipto_country,
            $shippingOrder->getCountryState()
        );

        return $shippingInfo;
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
