<?php

namespace HiPay\Payment\Tests\Tools;

use HiPay\Fullservice\Gateway\Mapper\HostedPaymentPageMapper;
use HiPay\Fullservice\Gateway\Mapper\TransactionMapper;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Payment\PaymentMethod\AbstractPaymentMethod;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * Usefull functions to test PaymentMethods.
 */
trait PaymentMethodMockTrait
{
    use ReadHipayConfigServiceMockTrait;
    use HipayHttpClientServiceMockTrait;
    use RequestStackMockTrait;

    protected function getPaymentMethod(string $classname, array $config, array $responses, Request $request = null): AbstractPaymentMethod
    {
        return new $classname(
            $this->createMock(OrderTransactionStateHandler::class),
            $this->getReadHipayConfig($config),
            $this->getClientService($responses),
            $this->getRequestStack($request),
            $this->getLocaleProvider()
        );
    }

    protected function generateTransaction(
        $config = []
    ) {
        $recurse = function (array $configs) use (&$recurse) {
            foreach ($configs as $key => $config) {
                if (is_array($config)) {
                    $isAssociativeArray = is_string($key);
                    foreach ($recurse($config) as $subKey => $subConfig) {
                        $configs[$isAssociativeArray ? $key.'.'.$subKey : $key] = $subConfig;
                    }
                }
            }

            return $configs;
        };
        $config = $recurse($config);
        // order
        $order = new OrderEntity();
        $order->setId($config['order.id'] ?? md5(rand(1, PHP_INT_MAX)));
        $order->setAmountTotal($config['order.amount_total'] ?? round(rand(1, PHP_INT_MAX), 2));
        $order->setShippingTotal($config['order.shipping_total'] ?? round(rand(1, PHP_INT_MAX), 2));
        $order->setAmountNet($config['order.amount_net'] ?? round(rand(1, PHP_INT_MAX), 2));

        // currency
        $currency = new CurrencyEntity();
        $currency->setIsoCode($config['order.currency.iso_code'] ?? 'FLOOZ');
        $order->setCurrency($currency);

        // lines items
        $lineItems = new OrderLineItemCollection();
        $lines = count($config['order.line_items'] ?? []) ?: random_int(0, 10);

        for ($i = 0; $i < $lines; ++$i) {
            $lineItem = new OrderLineItemEntity();
            $lineItem->setUniqueIdentifier($config['order.line_items'][$i]['id'] ?? md5($i));
            $lineItem->setType($config['order.line_items'][$i]['type'] ?? 'product');
            $lineItem->setQuantity($config['order.line_items'][$i]['quantity'] ?? random_int(0, 100));
            $lineItem->setLabel($config['order.line_items'][$i]['label'] ?? 'product '.$i);
            $lineItems->add($lineItem);
        }
        $order->setLineItems($lineItems);

        // order Customer
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId($config['order.order_customer.id'] ?? md5(rand(1, PHP_INT_MAX)));
        $orderCustomer->setRemoteAddress($config['order.order_customer.remote_address'] ?? '127.0.0.1');
        $orderCustomer->setEmail($config['order.order_customer.email'] ?? 'order.order_customer.email');

        $order->setOrderCustomer($orderCustomer);

        // Order billing
        $orderBilling = new OrderAddressEntity();
        $orderBilling->setFirstName($config['order.billing.first_name'] ?? 'order.billing.first_name');
        $orderBilling->setLastName($config['order.billing.last_name'] ?? 'order.billing.last_name');
        $orderBilling->setPhoneNumber($config['order.billing.phone_number'] ?? 'order.billing.phone_number');
        $orderBilling->setCompany($config['order.billing.company'] ?? 'order.billing.compagny');
        $orderBilling->setStreet($config['order.billing.street'] ?? 'order.billing.street');
        $orderBilling->setAdditionalAddressLine1($config['order.billing.additional_address_line1'] ?? 'order.billing.additional_address_line1');
        $orderBilling->setAdditionalAddressLine2($config['order.billing.additional_address_line2'] ?? 'order.billing.additional_address_line2');
        $orderBilling->setZipcode($config['order.billing.zip_code'] ?? 'order.billing.zip_code');
        $orderBilling->setCity($config['order.billing.city'] ?? 'order.billing.city');

        $salutation = new SalutationEntity();
        $salutation->setSalutationKey($config['order.billing.salutation.salutation_key'] ?? 'order.billing.salutation.salutation_key');
        $orderBilling->setSalutation($salutation);

        $country = new CountryEntity();
        $country->setIso($config['order.billing.country.iso'] ?? 'XX');
        $orderBilling->setCountry($country);

        if (isset($config['order.billing.state.name'])) {
            $countryState = new CountryStateEntity();
            $countryState->setName($config['order.billing.state.name']);
            $orderBilling->setCountryState($countryState);
        }

        $order->setBillingAddress($orderBilling);

        // Order Shipping
        $orderShipping = new OrderAddressEntity();
        $orderShipping->setFirstName($config['order.shipping.first_name'] ?? 'order.shipping.first_name');
        $orderShipping->setLastName($config['order.shipping.last_name'] ?? 'order.shipping.last_name');
        $orderShipping->setPhoneNumber($config['order.shipping.phone_number'] ?? 'order.shipping.phone_number');
        $orderShipping->setCompany($config['order.shipping.company'] ?? 'order.shipping.compagny');
        $orderShipping->setStreet($config['order.shipping.street'] ?? 'order.shipping.street');
        $orderShipping->setAdditionalAddressLine1($config['order.shipping.additional_address_line1'] ?? 'order.shipping.additional_address_line1');
        $orderShipping->setAdditionalAddressLine2($config['order.shipping.additional_address_line2'] ?? 'order.shipping.dditional_address_line2');
        $orderShipping->setZipcode($config['order.shipping.zip_code'] ?? 'order.shipping.zip_code');
        $orderShipping->setCity($config['order.shipping.city'] ?? 'order.shipping.city');

        $salutation = new SalutationEntity();
        $salutation->setSalutationKey($config['order.shipping.salutation.salutation_key'] ?? 'order.shipping.salutation.salutation_key');
        $orderShipping->setSalutation($salutation);

        $country = new CountryEntity();
        $country->setIso($config['order.shipping.country.iso'] ?? 'XX');
        $orderShipping->setCountry($country);

        if (isset($config['order.shipping.state.name'])) {
            $countryState = new CountryStateEntity();
            $countryState->setName($config['order.shipping.state.name']);
            $orderShipping->setCountryState($countryState);
        }

        $orderDelivery = new OrderDeliveryEntity();
        $orderDelivery->setShippingOrderAddress($orderShipping);
        $orderDelivery->setUniqueIdentifier(md5(rand(1, PHP_INT_MAX)));

        $orderShippingCollection = new OrderDeliveryCollection([$orderDelivery]);
        $order->setDeliveries($orderShippingCollection);

        // transaction
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($config['transaction.id'] ?? md5(rand(1, PHP_INT_MAX)));

        if (isset($config['transaction.payment_method.custom_fields'])) {
            $paymentMethod = new PaymentMethodEntity();
            $paymentMethod->setCustomFields($config['transaction.payment_method.custom_fields']);
            $orderTransaction->setPaymentMethod($paymentMethod);
        }

        /** @var AsyncPaymentTransactionStruct&MockObject */
        $transaction = $this->createMock(AsyncPaymentTransactionStruct::class);
        $transaction->method('getOrderTransaction')->willReturn($orderTransaction);
        $transaction->method('getOrder')->willReturn($order);
        $transaction->method('getReturnUrl')->willReturn($config['return_url'] ?? md5(rand(1, PHP_INT_MAX)));

        return $transaction;
    }

    protected function getLocaleProvider($locale = 'en-GB')
    {
        /** @var LocaleProvider&MockObject */
        $localeProvider = $this->createMock(LocaleProvider::class);
        $localeProvider->method('getLocaleFromContext')->willReturn($locale);

        return $localeProvider;
    }

    protected function getHostedFiledsOrderRequest(string $classname, array $jsonResponse = null): OrderRequest
    {
        $config = [
            'operationMode' => 'hostedFields',
            'captureMode' => 'auto',
        ];

        $orderRequest = new OrderRequest();
        $responses = [
            'requestNewOrder' => function (OrderRequest $argument) use (&$orderRequest) {
                $orderRequest = $argument;

                return (new TransactionMapper([
                    'foo' => 'bar',
                ]))->getModelObjectMapped();
            },
        ];

        if (null === $jsonResponse) {
            $jsonResponse = [
                'token' => md5(rand(PHP_INT_MIN, PHP_INT_MAX)),
            ];
        }

        $request = new Request([
            'hipay-response' => json_encode($jsonResponse),
        ]);

        $paymentMethod = $this->getPaymentMethod(
            $classname,
            $config,
            $responses,
            $request
        );
        $this->createMock(SalesChannelContext::class);

        $paymentMethod->pay(
            $this->generateTransaction(),
            $this->createMock(RequestDataBag::class),
            $this->createMock(SalesChannelContext::class)
        );

        return $orderRequest;
    }

    protected function getHostedPagePaymentRequest(string $classname, $redirectUri = null, array $transactionConfig = [])
    {
        $config = [
            'operationMode' => 'hostedPage',
            'captureMode' => 'auto',
        ];

        if (null === $redirectUri) {
            $redirectUri = md5(rand(PHP_INT_MIN, PHP_INT_MAX));
        }

        $hostedPaymentPageRequest = new HostedPaymentPageRequest();
        $responses = [
            'requestHostedPaymentPage' => function (HostedPaymentPageRequest $argument) use ($redirectUri, &$hostedPaymentPageRequest) {
                $hostedPaymentPageRequest = $argument;

                return (new HostedPaymentPageMapper([
                    'forwardUrl' => $redirectUri,
                ]))->getModelObjectMapped();
            },
        ];

        $paymentMethod = $this->getPaymentMethod(
            $classname,
            $config,
            $responses
        );

        $paymentMethod->pay(
            $this->generateTransaction($transactionConfig),
            $this->createMock(RequestDataBag::class),
            $this->createMock(SalesChannelContext::class)
        );

        return $hostedPaymentPageRequest;
    }
}
