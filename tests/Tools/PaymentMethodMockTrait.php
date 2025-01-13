<?php

namespace HiPay\Payment\Tests\Tools;

use HiPay\Fullservice\Gateway\Mapper\HostedPaymentPageMapper;
use HiPay\Fullservice\Gateway\Mapper\TransactionMapper;
use HiPay\Fullservice\Gateway\Model\Request\ThreeDSTwo\AccountInfo\Customer;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Payment\PaymentMethod\AbstractPaymentMethod;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
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

    protected function getPaymentMethod(string $classname, array $config, array $responses, Request $request = null, array $orderCustomerConfig = [], array $construct = []): AbstractPaymentMethod
    {
        return new $classname(
            $this->createMock(OrderTransactionStateHandler::class),
            $this->getReadHipayConfig($config),
            $this->getClientService($responses),
            $this->getRequestStack($request),
            $this->getLocaleProvider(),
            $this->generateOrderCustomerRepo($orderCustomerConfig),
            $this->createMock(LoggerInterface::class),
            ...$construct
        );
    }

    private function recurseConfig($configs)
    {
        foreach ($configs as $key => $config) {
            if (is_array($config)) {
                foreach ($this->recurseConfig($config) as $subKey => $subConfig) {
                    if (is_string($key) && is_string($subKey)) {
                        $configs[$key.'.'.$subKey] = $subConfig;
                    } else {
                        $configs[$key][$subKey] = $subConfig;
                    }
                }
            }
        }

        return $configs;
    }

    protected function generateTransaction(
        $config = []
    ) {
        $config = $this->recurseConfig($config);
        // order
        $order = new OrderEntity();
        $order->setId($config['order.id'] ?? md5(rand(1, PHP_INT_MAX)));
        $order->setAmountTotal($config['order.amount_total'] ?? round(rand(1, PHP_INT_MAX), 2));
        $order->setShippingTotal($config['order.shipping_total'] ?? round(rand(1, PHP_INT_MAX), 2));
        $order->setAmountNet($config['order.amount_net'] ?? round(rand(1, PHP_INT_MAX), 2));
        $order->setOrderNumber($config['order.order_number'] ?? rand(1, PHP_INT_MAX));

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
            $lineItem->setPayload($config['order.line_items'][$i]['payload'] ?? ['stock' => 1]);
            $lineItem->setProductId($config['order.line_items'][$i]['product_id'] ?? md5(rand(1, PHP_INT_MAX)));
            $lineItems->add($lineItem);
        }
        $order->setLineItems($lineItems);

        // Customer
        $customer = new CustomerEntity();
        $customer->setId($config['customer.id'] ?? md5(rand(1, PHP_INT_MAX)));
        $customer->setCreatedAt($config['customer.created_at'] ?? new \DateTime());

        // order Customer
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId($config['order.order_customer.id'] ?? md5(rand(1, PHP_INT_MAX)));
        $orderCustomer->setRemoteAddress($config['order.order_customer.remote_address'] ?? '127.0.0.1');
        $orderCustomer->setEmail($config['order.order_customer.email'] ?? 'order.order_customer.email');
        $orderCustomer->setCustomer($customer);

        $order->setOrderCustomer($orderCustomer);

        // Order billing
        $orderBilling = $orderShipping = $this->generateAddress($config, 'order.billing');
        $order->setBillingAddress($orderBilling);

        // Order Shipping
        $orderShipping = $this->generateAddress($config, 'order.shipping');

        $orderDelivery = new OrderDeliveryEntity();
        $orderDelivery->setShippingOrderAddress($orderShipping);
        $orderDelivery->setShippingDateEarliest($config['order.shipping.date_earliest'] ?? new \DateTime());
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

    private function generateOrderCustomerRepo(array $config = [])
    {
        $config = $this->recurseConfig($config);

        $orderCustomers = new OrderCustomerCollection();

        // Customer
        $customer = new CustomerEntity();
        $customer->setId($config['customer.id'] ?? md5(rand(1, PHP_INT_MAX)));
        $customer->setCreatedAt($config['customer.created_at'] ?? new \DateTime());

        $orderCustomerSize = count($config['order_customer'] ?? [1]);
        for ($o = 0; $o < $orderCustomerSize; ++$o) {
            $ocConfig = $config['order_customer'][$o] ?? [];

            // order Customer
            $orderCustomer = new OrderCustomerEntity();
            $orderCustomer->setId($ocConfig['id'] ?? md5(rand(1, PHP_INT_MAX)));
            $orderCustomer->setRemoteAddress($ocConfig['remote_address'] ?? '127.0.0.1');
            $orderCustomer->setEmail($ocConfig['email'] ?? 'order_customer.'.$o.'email');
            $orderCustomer->setCustomer($customer);

            // Order
            $order = new OrderEntity();
            $order->setId($ocConfig['order.id'] ?? md5(rand(1, PHP_INT_MAX)));
            $order->setCreatedAt($ocConfig['order.created_at'] ?? new \DateTime());

            // Order billing
            $orderBilling = $this->generateAddress($ocConfig, 'order.billing');
            $order->setBillingAddress($orderBilling);

            // Order Shipping
            $orderShipping = $this->generateAddress($ocConfig, 'order.shipping');

            $orderDelivery = new OrderDeliveryEntity();
            $orderDelivery->setShippingOrderAddress($orderShipping);
            $orderDelivery->setShippingDateEarliest($config['order.shipping.date_earliest'] ?? new \DateTime());
            $orderDelivery->setUniqueIdentifier(md5(rand(1, PHP_INT_MAX)));

            $orderShippingCollection = new OrderDeliveryCollection([$orderDelivery]);
            $order->setDeliveries($orderShippingCollection);

            // lines items
            $lineItems = new OrderLineItemCollection();
            $lines = count($ocConfig['order.line_items'] ?? []) ?: random_int(0, 10);

            for ($i = 0; $i < $lines; ++$i) {
                $lineItem = new OrderLineItemEntity();
                $lineItem->setUniqueIdentifier($ocConfig['order.line_items'][$i]['id'] ?? md5($i));
                $lineItem->setType($ocConfig['order.line_items'][$i]['type'] ?? 'product');
                $lineItem->setQuantity($ocConfig['order.line_items'][$i]['quantity'] ?? random_int(0, 100));
                $lineItem->setLabel($ocConfig['order.line_items'][$i]['label'] ?? 'product '.$i);
                $lineItem->setPayload($ocConfig['order.line_items'][$i]['payload'] ?? ['stock' => 1]);
                $lineItem->setProductId($ocConfig['order.line_items'][$i]['product_id'] ?? md5(rand(1, PHP_INT_MAX)));
                $lineItems->add($lineItem);
            }
            $order->setLineItems($lineItems);

            // transactions
            $transactions = new OrderTransactionCollection();
            $transactionSize = count($ocConfig['order.transactions'] ?? []) ?: random_int(0, 10);

            for ($i = 0; $i < $transactionSize; ++$i) {
                $paymentMethod = new PaymentMethodEntity();
                $paymentMethod->setHandlerIdentifier($ocConfig['order.transactions'][$i]['payment']['handler'] ?? '');

                $transaction = new OrderTransactionEntity();
                $transaction->setUniqueIdentifier($ocConfig['order.transactions'][$i]['id'] ?? md5($i));
                $transaction->setPaymentMethod($paymentMethod);
                $transaction->setOrderId($order->getId());

                $transactions->add($transaction);
            }
            $order->setTransactions($transactions);

            $orderCustomer->setOrder($order);
            $orderCustomer->setOrderId($order->getId());

            $orderCustomers->add($orderCustomer);
        }

        /** @var EntityRepository&MockObject */
        $searchEntity = new EntitySearchResult(
            OrderCustomerEntity::class,
            count($orderCustomers),
            $orderCustomers,
            $this->createMock(AggregationResultCollection::class),
            new Criteria(),
            Context::createDefaultContext()
        );

        /** @var EntityRepository&MockObject */
        $orderCustomerRepo = $this->createMock(EntityRepository::class);
        $orderCustomerRepo->method('search')->willReturn($searchEntity);

        return $orderCustomerRepo;
    }

    private function generateAddress(array $config, string $prefix)
    {
        $address = new OrderAddressEntity();
        $address->setUniqueIdentifier(md5(rand(1, PHP_INT_MAX)));
        $address->setFirstName($config[$prefix.'.first_name'] ?? $prefix.'.first_name');
        $address->setLastName($config[$prefix.'.last_name'] ?? $prefix.'.last_name');
        $address->setPhoneNumber($config[$prefix.'.phone_number'] ?? $prefix.'.phone_number');
        $address->setCompany($config[$prefix.'.company'] ?? $prefix.'.compagny');
        $address->setStreet($config[$prefix.'.street'] ?? $prefix.'.street');
        $address->setAdditionalAddressLine1($config[$prefix.'.additional_address_line1'] ?? $prefix.'.additional_address_line1');
        $address->setAdditionalAddressLine2($config[$prefix.'.additional_address_line2'] ?? $prefix.'.dditional_address_line2');
        $address->setZipcode($config[$prefix.'.zip_code'] ?? $prefix.'.zip_code');
        $address->setCity($config[$prefix.'.city'] ?? $prefix.'.city');

        $salutation = new SalutationEntity();
        $salutation->setId($config[$prefix.'.salutation.id'] ?? md5(rand(1, PHP_INT_MAX)));
        $salutation->setSalutationKey($config[$prefix.'.salutation.salutation_key'] ?? $prefix.'.salutation.salutation_key');
        $address->setSalutation($salutation);
        $address->setSalutationId($salutation->getId());

        $country = new CountryEntity();
        $country->setId($config[$prefix.'.country.id'] ?? md5(rand(1, PHP_INT_MAX)));
        $country->setIso($config[$prefix.'.country.iso'] ?? 'XX');
        $address->setCountry($country);
        $address->setCountryId($country->getId());

        if (isset($config[$prefix.'.state.name'])) {
            $countryState = new CountryStateEntity();
            $countryState->setName($config[$prefix.'.state.name']);
            $address->setCountryState($countryState);
        }

        return $address;
    }

    protected function getLocaleProvider($locale = 'en-GB')
    {
        /** @var LocaleProvider&MockObject */
        $localeProvider = $this->createMock(LocaleProvider::class);
        $localeProvider->method('getLocaleFromContext')->willReturn($locale);

        return $localeProvider;
    }

    protected function getHostedFiledsOrderRequest(string $classname, array $jsonResponse = null, $redirectUri = null, array $construct = [], array $configTransaction = []): OrderRequest
    {
        $config = [
            'operationMode' => 'hostedFields',
            'captureMode' => 'auto',
        ];

        if (null === $redirectUri) {
            $redirectUri = md5(rand(PHP_INT_MIN, PHP_INT_MAX));
        }

        $orderRequest = new OrderRequest();
        $responses = [
            'requestNewOrder' => function (OrderRequest $argument) use (&$orderRequest, $redirectUri) {
                $orderRequest = $argument;

                return (new TransactionMapper([
                    'forward_url' => $redirectUri,
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
            $request,
            [],
            $construct
        );
        $this->createMock(SalesChannelContext::class);

        $paymentMethod->pay(
            $this->generateTransaction($configTransaction),
            $this->createMock(RequestDataBag::class),
            $this->createMock(SalesChannelContext::class)
        );

        return $orderRequest;
    }

    protected function getHostedPagePaymentRequest(string $classname, $redirectUri = null, array $transactionConfig = [], array $construct = [])
    {
        $config = [
            'operationMode' => 'hostedPage',
            'captureMode' => 'auto',
            'cancelButton' => true
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
            $responses,
            null,
            [],
            $construct
        );

        $paymentMethod->pay(
            $this->generateTransaction($transactionConfig),
            $this->createMock(RequestDataBag::class),
            $this->createMock(SalesChannelContext::class)
        );

        return $hostedPaymentPageRequest;
    }
}
