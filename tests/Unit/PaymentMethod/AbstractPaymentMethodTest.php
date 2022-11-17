<?php

namespace Hipay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Fullservice\Enum\ThreeDSTwo\DeviceChannel;
use HiPay\Fullservice\Gateway\Mapper\HostedPaymentPageMapper;
use HiPay\Fullservice\Gateway\Mapper\TransactionMapper;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Payment\PaymentMethod\AbstractPaymentMethod;
use HiPay\Payment\Service\HiPayHttpClientService;
use HiPay\Payment\Service\ReadHipayConfigService;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;

class AbstractPaymentMethodTest extends TestCase
{
    use PaymentMethodMockTrait;

    private function generateSubClass(
        OrderTransactionStateHandler $transactionStateHandler,
        ReadHipayConfigService $config,
        HiPayHttpClientService $clientService,
        RequestStack $requestStack,
        LocaleProvider $localeProvider
    ) {
        return $subClass = new class(...func_get_args()) extends AbstractPaymentMethod {
            public static function getName(string $code): string
            {
                return static::class;
            }

            public static function getDescription(string $code): string
            {
                return static::class;
            }

            protected function hydrateHostedFields(OrderRequest $orderRequest): OrderRequest
            {
                return $orderRequest;
            }

            protected function hydrateHostedPage(HostedPaymentPageRequest $orderRequest, AsyncPaymentTransactionStruct $transaction): HostedPaymentPageRequest
            {
                return $orderRequest;
            }
        };

        return $subClass;
    }

    public function testPayValidWithHostedFields()
    {
        $configTransaction = [
            'return_url' => 'foo.bar',
            'order' => [
                'shipping_total' => 1.34,
                'amount_total' => 123.4,
                'amount_net' => 99.99,
                'currency' => [
                    'iso_code' => 'FLZ',
                ],
                'order_customer' => [
                    'id' => 'DonaldDuck',
                    'remote_address' => '8.8.8.8',
                    'email' => 'donald@duck.cv',
                ],
                'billing' => [
                    'first_name' => 'Donald',
                    'last_name' => 'Duck',
                    'phone_number' => '+C01N C01N',
                    'company' => 'compagny_donald',
                    'street' => '23 rue des canards',
                    'additional_address_line1' => ' dans les roseaux ',
                    'additional_address_line2' => ' près du lac ',
                    'zip_code' => '12345',
                    'city' => 'CANARDVILLE',
                    'salutation.salutation_key' => 'mr',
                    'gender_expected' => 'M',
                    'country.iso' => 'DY',
                    'state.name' => 'MikeyState',
                    'state_expected' => null,
                ],
                'shipping' => [
                    'first_name' => 'Daisy',
                    'last_name' => 'Duck',
                    'phone_number' => '+N01C N01C',
                    'company' => 'compagny_daisy',
                    'street' => '75 rue du pain mouilllé',
                    'additional_address_line1' => ' dans la mare ',
                    'additional_address_line2' => ' aux canards ',
                    'zip_code' => '54321',
                    'city' => 'CANARDCITY',
                    'salutation.salutation_key' => 'mrs',
                    'country.iso' => 'CA',
                    'state.name' => 'canardState',
                    'gender_expected' => 'F',
                    'state_expected' => 'canardState',
                ],
                'line_items' => [
                    [
                        'label' => str_repeat('one product with long description', 10),
                        'quantity' => 300,
                    ],
                    [
                        'label' => 'another product',
                        'quantity' => 1,
                    ],
                ],
                'expected_description' => '300 x one product with long descriptionone product with long descriptionone product with long descriptionone product with long descriptionone product with long descriptionone product with long descriptionone product with long descriptionone product wit...',
            ],
            'transaction' => [
                'id' => 'azertyuiop',
            ],
        ];

        $config = [
            'operationMode' => 'hostedFields',
            'captureMode' => 'automatic',
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

        $locale = 'ab_CD';

        $paymentMethod = $this->generateSubClass(
            $this->createMock(OrderTransactionStateHandler::class),
            $this->getReadHipayConfig($config),
            $this->getClientService($responses),
            $this->getRequestStack(),
            $this->getLocaleProvider($locale)
        );

        $redirectRequest = $paymentMethod->pay(
            $this->generateTransaction($configTransaction),
            $this->createMock(RequestDataBag::class),
            $this->createMock(SalesChannelContext::class)
        );

        $this->assertEquals(
            $configTransaction['return_url'],
            $redirectRequest->getTargetUrl(),
            'return_url missmatch'
        );

        $this->assertEquals(
            'automatic' !== $config['captureMode'] ? 'Authorization' : 'Sale',
            $orderRequest->operation,
            'operation missmatch'
        );

        $this->assertEquals(
            $locale,
            $orderRequest->language,
            'language missmatch'
        );

        $this->orderRequestTesting($orderRequest, $configTransaction);
    }

    public function testPayValidWithHostedPage()
    {
        $configTransaction = [
            'return_url' => 'foo.bar',
            'order' => [
                'shipping_total' => 10,
                'amount_total' => 100_000,
                'amount_net' => 90_000,
                'currency' => [
                    'iso_code' => 'FLZ',
                ],
                'order_customer' => [
                    'id' => 'DonaldDuck',
                    'remote_address' => '8.8.8.8',
                    'email' => 'donald@duck.cv',
                ],
                'billing' => [
                    'first_name' => 'Donald',
                    'last_name' => 'Duck',
                    'phone_number' => '+C01N C01N',
                    'company' => 'compagny_donald',
                    'street' => '23 rue des canards',
                    'additional_address_line1' => '  ',
                    'additional_address_line2' => ' près du lac ',
                    'zip_code' => '12345',
                    'city' => 'CANARDVILLE',
                    'salutation.salutation_key' => 'mr',
                    'gender_expected' => 'M',
                    'country.iso' => 'DY',
                    'state.name' => 'MikeyState',
                    'state_expected' => null,
                ],
                'shipping' => [
                    'first_name' => 'Daisy',
                    'last_name' => 'Duck',
                    'phone_number' => '+N01C N01C',
                    'company' => 'compagny_daisy',
                    'street' => '75 rue du pain mouilllé',
                    'additional_address_line1' => ' dans la mare ',
                    'additional_address_line2' => ' ',
                    'zip_code' => '54321',
                    'city' => 'CANARDCITY',
                    'salutation.salutation_key' => 'mrs',
                    'country.iso' => 'CA',
                    'state.name' => 'canardState',
                    'gender_expected' => 'F',
                    'state_expected' => 'canardState',
                ],
                'line_items' => [
                    [
                        'label' => 'juste one product',
                        'quantity' => 1,
                    ],
                ],
                'expected_description' => '1 x juste one product',
            ],
            'transaction' => [
                'id' => 'wxcvbn',
            ],
        ];

        $redirectUri = 'foo.bar';

        $config = [
            'operationMode' => 'hostedPage',
            'captureMode' => 'auto',
        ];

        $hostedPaymentPageRequest = new HostedPaymentPageRequest();
        $responses = [
            'requestHostedPaymentPage' => function (HostedPaymentPageRequest $argument) use ($redirectUri, &$hostedPaymentPageRequest) {
                $hostedPaymentPageRequest = $argument;

                return (new HostedPaymentPageMapper([
                    'forwardUrl' => $redirectUri,
                ]))->getModelObjectMapped();
            },
        ];

        $paymentMethod = $this->generateSubClass(
            $this->createMock(OrderTransactionStateHandler::class),
            $this->getReadHipayConfig($config),
            $this->getClientService($responses),
            $this->getRequestStack(),
            $this->getLocaleProvider()
        );

        /** @var RequestDataBag&MockObject */
        $dataBag = $this->createMock(RequestDataBag::class);

        /** @var SalesChannelContext&MockObject */
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $redirectRequest = $paymentMethod->pay(
            $this->generateTransaction($configTransaction),
            $dataBag,
            $salesChannelContext
        );

        $this->assertEquals(
            $redirectUri,
            $redirectRequest->getTargetUrl()
        );

        $this->assertEquals(
            'en_GB',
            $hostedPaymentPageRequest->language,
            'language missmatch'
        );

        $this->orderRequestTesting($hostedPaymentPageRequest, $configTransaction);
    }

    public function testPayFail()
    {
        $config = [
            'operationMode' => 'hostedPage',
            'captureMode' => 'auto',
        ];

        $responses = [
            'requestHostedPaymentPage' => function () {
                throw new \Exception('Random Exception');
            },
        ];

        $paymentMethod = $this->generateSubClass(
            $this->createMock(OrderTransactionStateHandler::class),
            $this->getReadHipayConfig($config),
            $this->getClientService($responses),
            $this->getRequestStack(),
            $this->getLocaleProvider()
        );

        $this->expectException(AsyncPaymentProcessException::class);
        $this->expectExceptionMessage('An error occurred during the communication with external payment gateway'.PHP_EOL.'Random Exception');

        /** @var RequestDataBag&MockObject */
        $dataBag = $this->createMock(RequestDataBag::class);

        /** @var SalesChannelContext&MockObject */
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $paymentMethod->pay(
            $this->generateTransaction(),
            $dataBag,
            $salesChannelContext
        );
    }

    public function testPayWithInvalidOperationMode()
    {
        $config = [
            'operationMode' => 'Foobar',
            'captureMode' => 'auto',
        ];

        $paymentMethod = $this->generateSubClass(
            $this->createMock(OrderTransactionStateHandler::class),
            $this->getReadHipayConfig($config),
            $this->getClientService(),
            $this->getRequestStack(),
            $this->getLocaleProvider()
        );

        $this->expectException(AsyncPaymentProcessException::class);
        $this->expectExceptionMessage('An error occurred during the communication with external payment gateway'.PHP_EOL.'Configuration mode "Foobar" is invalid');

        /** @var RequestDataBag&MockObject */
        $dataBag = $this->createMock(RequestDataBag::class);

        /** @var SalesChannelContext&MockObject */
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $paymentMethod->pay(
            $this->generateTransaction(),
            $dataBag,
            $salesChannelContext
        );
    }

    public function orderRequestTesting(OrderRequest $orderRequest, array $configTransaction)
    {
        $this->assertEquals(
            $configTransaction['transaction']['id'],
            $orderRequest->orderid,
            'orderid missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['expected_description'],
            $orderRequest->description,
            'description is too long'
        );

        $this->assertEquals(
            $configTransaction['order']['currency']['iso_code'],
            $orderRequest->currency,
            'currency missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['shipping_total'],
            $orderRequest->shipping,
            'shipping missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['amount_total'],
            $orderRequest->amount,
            'amount missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['amount_total'] - $configTransaction['order']['amount_net'],
            $orderRequest->tax,
            'tax missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['order_customer']['remote_address'],
            $orderRequest->ipaddr,
            'ipaddr missmatch'
        );

        $this->assertEquals(
            'application/json',
            $orderRequest->http_accept,
            'http_accept missmatch'
        );

        $this->assertEquals(
            DeviceChannel::BROWSER,
            $orderRequest->device_channel,
            'device_channel missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['order_customer']['id'],
            $orderRequest->cid,
            'cid missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['billing']['first_name'],
            $orderRequest->customerBillingInfo->firstname,
            'firstname billing info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['billing']['last_name'],
            $orderRequest->customerBillingInfo->lastname,
            'lastname billing info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['order_customer']['email'],
            $orderRequest->customerBillingInfo->email,
            'email billing info missmatch'
        );

        // Billing
        $this->assertEquals(
            $configTransaction['order']['billing']['phone_number'],
            $orderRequest->customerBillingInfo->phone,
            'phone billing info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['billing']['gender_expected'],
            $orderRequest->customerBillingInfo->gender,
            'gender billing info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['billing']['company'],
            $orderRequest->customerBillingInfo->recipientinfo,
            'recipientinfo billing info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['billing']['street'],
            $orderRequest->customerBillingInfo->streetaddress,
            'streetaddress billing info missmatch'
        );

        $this->assertEquals(
            trim(
                trim($configTransaction['order']['billing']['additional_address_line1']).' '.trim($configTransaction['order']['billing']['additional_address_line2'])
            ),
            $orderRequest->customerBillingInfo->streetaddress2,
            'streetaddress2 billing info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['billing']['zip_code'],
            $orderRequest->customerBillingInfo->zipcode,
            'zipcode billing info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['billing']['city'],
            $orderRequest->customerBillingInfo->city,
            'city billing info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['billing']['country.iso'],
            $orderRequest->customerBillingInfo->country,
            'country billing info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['billing']['state_expected'],
            $orderRequest->customerBillingInfo->state,
            'state billing info missmatch'
        );

        // Shipping
        $this->assertEquals(
            $configTransaction['order']['shipping']['phone_number'],
            $orderRequest->customerShippingInfo->shipto_phone,
            'phone shipping info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['shipping']['gender_expected'],
            $orderRequest->customerShippingInfo->shipto_gender,
            'gender shipping info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['shipping']['company'],
            $orderRequest->customerShippingInfo->shipto_recipientinfo,
            'recipientinfo shipping info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['shipping']['street'],
            $orderRequest->customerShippingInfo->shipto_streetaddress,
            'streetaddress shipping info missmatch'
        );

        $this->assertEquals(
            trim(
                trim($configTransaction['order']['shipping']['additional_address_line1']).' '.trim($configTransaction['order']['shipping']['additional_address_line2'])
            ),
            $orderRequest->customerShippingInfo->shipto_streetaddress2,
            'streetaddress2 shipping info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['shipping']['zip_code'],
            $orderRequest->customerShippingInfo->shipto_zipcode,
            'zipcode shipping info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['shipping']['city'],
            $orderRequest->customerShippingInfo->shipto_city,
            'city shipping info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['shipping']['country.iso'],
            $orderRequest->customerShippingInfo->shipto_country,
            'country shipping info missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['shipping']['state_expected'],
            $orderRequest->customerShippingInfo->shipto_state,
            'state shipping info missmatch'
        );
    }
}
