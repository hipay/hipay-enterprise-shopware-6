<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Enum\ThreeDSTwo\DeliveryTimeFrame;
use HiPay\Fullservice\Enum\ThreeDSTwo\DeviceChannel;
use HiPay\Fullservice\Enum\ThreeDSTwo\PurchaseIndicator;
use HiPay\Fullservice\Enum\ThreeDSTwo\ReorderIndicator;
use HiPay\Fullservice\Enum\ThreeDSTwo\ShippingIndicator;
use HiPay\Fullservice\Enum\Transaction\TransactionState;
use HiPay\Fullservice\Gateway\Mapper\HostedPaymentPageMapper;
use HiPay\Fullservice\Gateway\Mapper\TransactionMapper;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Payment\PaymentMethod\AbstractPaymentMethod;
use HiPay\Payment\Service\HiPayHttpClientService;
use HiPay\Payment\Service\ReadHipayConfigService;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AbstractPaymentMethodTest extends TestCase
{
    use PaymentMethodMockTrait;

    private function generateSubClass(
        OrderTransactionStateHandler $transactionStateHandler,
        ReadHipayConfigService $config,
        HiPayHttpClientService $clientService,
        RequestStack $requestStack,
        LocaleProvider $localeProvider,
        EntityRepository $orderCustomerRepository,
        LoggerInterface $logger,
        bool $haveHostedFields = false
    ) {
        return $subClass = new class(...func_get_args()) extends AbstractPaymentMethod {
            protected const PAYMENT_POSITION = 1;

            protected static bool $haveHostedFields = false;
            protected static bool $allowPartialCapture = true;
            protected static bool $allowPartialRefund = true;

            public function __construct(
                OrderTransactionStateHandler $transactionStateHandler,
                ReadHipayConfigService $config,
                HiPayHttpClientService $clientService,
                RequestStack $requestStack,
                LocaleProvider $localeProvider,
                EntityRepository $orderCustomerRepository,
                LoggerInterface $logger,
                bool $haveHostedFields = false
            ) {
                static::$haveHostedFields = $haveHostedFields;
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

            public static function getProductCode(): string
            {
                return 'foobar';
            }

            protected static function loadPaymentConfig(): PaymentProduct
            {
                return new PaymentProduct([
                    'allowPartialCapture' => static::$allowPartialCapture,
                    'allowPartialRefund' => static::$allowPartialRefund,
                ]);
            }

            public static function getName(string $code): string
            {
                return static::class;
            }

            public static function getDescription(string $code): string
            {
                return static::class;
            }

            protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, AsyncPaymentTransactionStruct $transaction): OrderRequest
            {
                return $orderRequest;
            }

            protected function hydrateHostedPage(HostedPaymentPageRequest $orderRequest, AsyncPaymentTransactionStruct $transaction): HostedPaymentPageRequest
            {
                return $orderRequest;
            }

            public static function getImage(): ?string
            {
                return null;
            }

            public static function getRule(ContainerInterface $container): ?array
            {
                return null;
            }

            public static function getPosition(): int
            {
                return 0;
            }
        };

        return $subClass;
    }

    public function testPayValidWithHostedFields()
    {
        $returnUrl = 'foo.bar';

        $configTransaction = [
            'return_url' => $returnUrl,
            'order' => [
                'order_number' => 98765,
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
                    'phone_number' => '+4930932107754',
                    'company' => 'compagny_donald',
                    'street' => '23 rue des canards',
                    'additional_address_line1' => ' dans les roseaux ',
                    'additional_address_line2' => ' près du lac ',
                    'zip_code' => '12345',
                    'city' => 'CANARDVILLE',
                    'salutation.salutation_key' => 'mr',
                    'gender_expected' => 'M',
                    'country.iso' => 'DE',
                    'state.name' => 'MikeyState',
                    'state_expected' => null,
                ],
                'shipping' => [
                    'first_name' => 'Daisy',
                    'last_name' => 'Duck',
                    'phone_number' => '+16135550165',
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
                    'state' => TransactionState::PENDING,
                ]))->getModelObjectMapped();
            },
        ];

        $locale = 'ab_CD';

        /** @var OrderTransactionStateHandler&MockObject $handler */
        $handler = $this->createMock(OrderTransactionStateHandler::class);

        $paymentMethod = $this->generateSubClass(
            $handler,
            $this->getReadHipayConfig($config),
            $this->getClientService($responses),
            $this->getRequestStack(),
            $this->getLocaleProvider($locale),
            $this->generateOrderCustomerRepo(),
            $this->createMock(LoggerInterface::class),
            true
        );

        $transaction = $this->generateTransaction($configTransaction);

        $redirectResponse = $paymentMethod->pay(
            $transaction,
            $this->createMock(RequestDataBag::class),
            $this->createMock(SalesChannelContext::class)
        );

        $this->assertEquals(
            $configTransaction['return_url'],
            $redirectResponse->getTargetUrl(),
            'return_url missmatch'
        );

        $this->assertNotNull(
            $orderRequest->custom_data['operation_id'],
            'custom field operation_id is empty'
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

        $handler->expects($this->once())
            ->method('process')
            ->with($this->equalTo($transaction->getOrderTransaction()->getId()));

        $paymentMethod->finalize(
            $transaction,
            new Request(),
            $this->createMock(SalesChannelContext::class)
        );
    }

    public function testForwardedWithHostedFields()
    {
        $forwardUrl = 'foo.bar';

        $config = [
            'operationMode' => 'hostedFields',
            'captureMode' => 'automatic',
        ];

        $orderRequest = new OrderRequest();
        $responses = [
            'requestNewOrder' => function (OrderRequest $argument) use (&$orderRequest, $forwardUrl) {
                $orderRequest = $argument;

                return (new TransactionMapper([
                    'state' => TransactionState::FORWARDING,
                    'forward_url' => $forwardUrl,
                ]))->getModelObjectMapped();
            },
        ];

        $locale = 'ab_CD';

        /** @var OrderTransactionStateHandler&MockObject $handler */
        $handler = $this->createMock(OrderTransactionStateHandler::class);

        $paymentMethod = $this->generateSubClass(
            $handler,
            $this->getReadHipayConfig($config),
            $this->getClientService($responses),
            $this->getRequestStack(),
            $this->getLocaleProvider($locale),
            $this->generateOrderCustomerRepo(),
            $this->createMock(LoggerInterface::class),
            true
        );

        $transaction = $this->generateTransaction();

        $redirectResponse = $paymentMethod->pay(
            $transaction,
            $this->createMock(RequestDataBag::class),
            $this->createMock(SalesChannelContext::class)
        );

        $this->assertSame(
            $forwardUrl,
            $redirectResponse->getTargetUrl()
        );
    }

    public static function provideTestFailWithHostedFields()
    {
        return [
            [TransactionState::ERROR],
            [TransactionState::DECLINED],
        ];
    }

    #[DataProvider('provideTestFailWithHostedFields')]
    public function testFailWithHostedFields($state)
    {
        $redirectUri = 'foo.bar';

        $configTransaction = [
            'return_url' => $redirectUri,
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
            'requestNewOrder' => function (OrderRequest $argument) use (&$orderRequest, $state) {
                $orderRequest = $argument;

                return (new TransactionMapper([
                    'state' => $state,
                ]))->getModelObjectMapped();
            },
        ];

        $locale = 'ab_CD';

        /** @var OrderTransactionStateHandler&MockObject $handler */
        $handler = $this->createMock(OrderTransactionStateHandler::class);

        $paymentMethod = $this->generateSubClass(
            $handler,
            $this->getReadHipayConfig($config),
            $this->getClientService($responses),
            $this->getRequestStack(),
            $this->getLocaleProvider($locale),
            $this->generateOrderCustomerRepo(),
            $this->createMock(LoggerInterface::class),
            true
        );

        $transaction = $this->generateTransaction($configTransaction);

        $redirectResponse = $paymentMethod->pay(
            $transaction,
            $this->createMock(RequestDataBag::class),
            $this->createMock(SalesChannelContext::class)
        );

        $this->assertSame(
            $redirectUri.'&return='.$state,
            $redirectResponse->getTargetUrl()
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Payment '.$state);

        $paymentMethod->finalize(
            $transaction,
            new Request(['return' => $state]),
            $this->createMock(SalesChannelContext::class)
        );
    }

    public function testPayValidWithHostedPage()
    {
        $configTransaction = [
            'return_url' => 'foo.bar',
            'order' => [
                'order_number' => 12_345,
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
                    'phone_number' => '+4930932107754',
                    'company' => 'compagny_donald',
                    'street' => '23 rue des canards',
                    'additional_address_line1' => '  ',
                    'additional_address_line2' => ' près du lac ',
                    'zip_code' => '12345',
                    'city' => 'CANARDVILLE',
                    'salutation.salutation_key' => 'mr',
                    'gender_expected' => 'M',
                    'country.iso' => 'DE',
                    'state.name' => 'MikeyState',
                    'state_expected' => null,
                ],
                'shipping' => [
                    'first_name' => 'Daisy',
                    'last_name' => 'Duck',
                    'phone_number' => '+16135550165',
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
            'cancelButton' => true
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
            $this->getLocaleProvider(),
            $this->generateOrderCustomerRepo(),
            $this->createMock(LoggerInterface::class)
        );

        /** @var RequestDataBag&MockObject */
        $dataBag = $this->createMock(RequestDataBag::class);

        /** @var SalesChannelContext&MockObject */
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $redirectResponse = $paymentMethod->pay(
            $this->generateTransaction($configTransaction),
            $dataBag,
            $salesChannelContext
        );

        $this->assertEquals(
            $redirectUri,
            $redirectResponse->getTargetUrl()
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
            'cancelButton' => true
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
            $this->getLocaleProvider(),
            $this->generateOrderCustomerRepo(),
            $this->createMock(LoggerInterface::class)
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('An error occurred during the communication with external payment gateway : Random Exception');

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
            $this->getLocaleProvider(),
            $this->generateOrderCustomerRepo(),
            $this->createMock(LoggerInterface::class),
            true
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('An error occurred during the communication with external payment gateway : Configuration mode "Foobar" is invalid');

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
            $orderRequest->custom_data['transaction_id'],
            'transaction_id missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['order_number'].'-'.dechex(crc32($configTransaction['transaction']['id'])),
            $orderRequest->orderid,
            'orderid missmatch'
        );

        $this->assertEquals(
            $configTransaction['order']['expected_description'],
            $orderRequest->description,
            'description is too long'
        );

        $this->assertLessThanOrEqual(
            255,
            strlen($orderRequest->description),
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

        $this->assertEquals(
            ShippingIndicator::SHIP_TO_DIFFERENT_ADDRESS,
            $orderRequest->merchant_risk_statement->shipping_indicator
        );

        $this->assertIsInt($orderRequest->account_info->customer->account_change);
        $this->assertIsInt($orderRequest->account_info->customer->opening_account_date);

        // urls
        $this->assertSame(
            'http://:/api/hipay/notify',
            $orderRequest->notify_url
        );

        $this->assertSame(
            ($orderRequest->forward_url ?? $configTransaction['return_url']).'&return=error',
            $orderRequest->decline_url
        );
        $this->assertSame(
            ($orderRequest->forward_url ?? $configTransaction['return_url']).'&return=error',
            $orderRequest->exception_url
        );
        $this->assertSame(
            ($orderRequest->forward_url ?? $configTransaction['return_url']).'&return=error',
            $orderRequest->cancel_url
        );
    }

    public static function provideTestDeliveryTimeFrame()
    {
        return [
            ['5 days', DeliveryTimeFrame::TWO_DAY_OR_MORE_SHIPPING],
            ['2 days', DeliveryTimeFrame::TWO_DAY_OR_MORE_SHIPPING],
            ['3 hours', DeliveryTimeFrame::OVERNIGHT_SHIPPING],
            ['1 day', DeliveryTimeFrame::SAME_DAY_SHIPPING],
        ];
    }

    #[DataProvider('provideTestDeliveryTimeFrame')]
    public function testDeliveryTimeFrame($delayFromToday, $expect)
    {
        $configTransaction = [
            'order' => [
                'shipping' => [
                    'date_earliest' => (new \DateTime())->sub(\DateInterval::createFromDateString('+ '.$delayFromToday)),
                ],
            ],
        ];

        $config = [
            'operationMode' => 'hostedFields',
            'captureMode' => 'automatic',
        ];

        $request = new OrderRequest();
        $responses = [
            'requestNewOrder' => function (OrderRequest $argument) use (&$request) {
                $request = $argument;

                return (new TransactionMapper([
                    'forward_url' => 'url',
                ]))->getModelObjectMapped();
            },
        ];

        $paymentMethod = $this->generateSubClass(
            $this->createMock(OrderTransactionStateHandler::class),
            $this->getReadHipayConfig($config),
            $this->getClientService($responses),
            $this->getRequestStack(),
            $this->getLocaleProvider(),
            $this->generateOrderCustomerRepo(),
            $this->createMock(LoggerInterface::class),
            true
        );

        /** @var RequestDataBag&MockObject */
        $dataBag = $this->createMock(RequestDataBag::class);

        /** @var SalesChannelContext&MockObject */
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $paymentMethod->pay(
            $this->generateTransaction($configTransaction),
            $dataBag,
            $salesChannelContext
        );

        $this->assertEquals(
            $expect,
            $request->merchant_risk_statement->delivery_time_frame
        );
    }

    public static function provideTestPurchaseIndicator()
    {
        return [
            [10, PurchaseIndicator::MERCHANDISE_AVAILABLE],
            [0, PurchaseIndicator::FUTURE_AVAILABILITY],
        ];
    }

    #[DataProvider('provideTestPurchaseIndicator')]
    public function testPurchaseIndicator($stock, $expect)
    {
        $configTransaction = [
            'order' => [
                'line_items' => [
                    [
                        'payload' => ['stock' => $stock],
                    ],
                ],
            ],
        ];

        $config = [
            'operationMode' => 'hostedFields',
            'captureMode' => 'automatic',
        ];

        $request = new OrderRequest();
        $responses = [
            'requestNewOrder' => function (OrderRequest $argument) use (&$request) {
                $request = $argument;

                return (new TransactionMapper([
                    'forward_url' => 'url',
                ]))->getModelObjectMapped();
            },
        ];

        $paymentMethod = $this->generateSubClass(
            $this->createMock(OrderTransactionStateHandler::class),
            $this->getReadHipayConfig($config),
            $this->getClientService($responses),
            $this->getRequestStack(),
            $this->getLocaleProvider(),
            $this->generateOrderCustomerRepo(),
            $this->createMock(LoggerInterface::class),
            true
        );

        /** @var RequestDataBag&MockObject */
        $dataBag = $this->createMock(RequestDataBag::class);

        /** @var SalesChannelContext&MockObject */
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $paymentMethod->pay(
            $this->generateTransaction($configTransaction),
            $dataBag,
            $salesChannelContext
        );

        $this->assertEquals(
            $expect,
            $request->merchant_risk_statement->purchase_indicator
        );
    }

    public static function provideTestReorderIndicator()
    {
        return [
            [true, ReorderIndicator::REORDERED],
            [false, ReorderIndicator::FIRST_TIME_ORDERED],
        ];
    }

    #[DataProvider('provideTestReorderIndicator')]
    public function testReorderIndicator($reordered, $expect)
    {
        $configTransaction = [
            'return_url' => 'foo.bar',
            'order' => [
                'line_items' => [
                    [
                        'product_id' => 'FGH',
                        'quantity' => 9,
                    ],
                    [
                        'product_id' => 'ABC',
                        'quantity' => 10,
                    ],
                ],
            ],
        ];

        $orderCutomerConfig = [];
        if ($reordered) {
            $orderCutomerConfig = [
                'order_customer' => [
                    [
                        'order' => [
                            'id' => 'FOO',
                            'line_items' => [
                                $configTransaction['order']['line_items'][1],
                                $configTransaction['order']['line_items'][0],
                            ],
                        ],
                    ],
                ],
            ];
        }

        $config = [
            'operationMode' => 'hostedFields',
            'captureMode' => 'automatic',
        ];

        $request = new OrderRequest();
        $responses = [
            'requestNewOrder' => function (OrderRequest $argument) use (&$request) {
                $request = $argument;

                return (new TransactionMapper([
                    'forward_url' => 'url',
                ]))->getModelObjectMapped();
            },
        ];

        $paymentMethod = $this->generateSubClass(
            $this->createMock(OrderTransactionStateHandler::class),
            $this->getReadHipayConfig($config),
            $this->getClientService($responses),
            $this->getRequestStack(),
            $this->getLocaleProvider(),
            $this->generateOrderCustomerRepo($orderCutomerConfig),
            $this->createMock(LoggerInterface::class),
            true
        );

        /** @var RequestDataBag&MockObject */
        $dataBag = $this->createMock(RequestDataBag::class);

        /** @var SalesChannelContext&MockObject */
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $paymentMethod->pay(
            $this->generateTransaction($configTransaction),
            $dataBag,
            $salesChannelContext
        );

        $this->assertEquals(
            $expect,
            $request->merchant_risk_statement->reorder_indicator
        );
    }

    public function testShippingIndicatorShipToCardHolderBillingAddress()
    {
        $configTransaction = [
            'return_url' => 'foo.bar',
            'order' => [
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
                    'salutation.id' => md5('salutation.id'),
                    'country.iso' => 'DY',
                    'country.id' => md5('country.id'),
                    'state.name' => 'MikeyState',
                ],
            ],
        ];

        $configTransaction['order']['shipping'] = $configTransaction['order']['billing'];

        $config = [
            'operationMode' => 'hostedFields',
            'captureMode' => 'automatic',
        ];

        $request = new OrderRequest();
        $responses = [
            'requestNewOrder' => function (OrderRequest $argument) use (&$request) {
                $request = $argument;

                return (new TransactionMapper([
                    'forward_url' => 'url',
                ]))->getModelObjectMapped();
            },
        ];

        $paymentMethod = $this->generateSubClass(
            $this->createMock(OrderTransactionStateHandler::class),
            $this->getReadHipayConfig($config),
            $this->getClientService($responses),
            $this->getRequestStack(),
            $this->getLocaleProvider(),
            $this->generateOrderCustomerRepo(),
            $this->createMock(LoggerInterface::class),
            true
        );

        /** @var RequestDataBag&MockObject */
        $dataBag = $this->createMock(RequestDataBag::class);

        /** @var SalesChannelContext&MockObject */
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $paymentMethod->pay(
            $this->generateTransaction($configTransaction),
            $dataBag,
            $salesChannelContext
        );

        $this->assertEquals(
            ShippingIndicator::SHIP_TO_CARDHOLDER_BILLING_ADDRESS,
            $request->merchant_risk_statement->shipping_indicator
        );
    }

    public function testShippingIndicatorShipToVerifiedAddress()
    {
        $configTransaction = [
            'return_url' => 'foo.bar',
            'order' => [
                'shipping' => [
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
                    'salutation.id' => md5('salutation.id'),
                    'country.iso' => 'DY',
                    'country.id' => md5('country.id'),
                    'state.name' => 'MikeyState',
                ],
            ],
        ];

        $config = [
            'operationMode' => 'hostedFields',
            'captureMode' => 'automatic',
        ];

        $request = new OrderRequest();
        $responses = [
            'requestNewOrder' => function (OrderRequest $argument) use (&$request) {
                $request = $argument;

                return (new TransactionMapper([
                    'forward_url' => 'url',
                ]))->getModelObjectMapped();
            },
        ];

        $orderCutomerConfig = [
            'order_customer' => [
                [
                    'order' => [
                        'shipping' => $configTransaction['order']['shipping'],
                    ],
                ],
            ],
        ];

        $paymentMethod = $this->generateSubClass(
            $this->createMock(OrderTransactionStateHandler::class),
            $this->getReadHipayConfig($config),
            $this->getClientService($responses),
            $this->getRequestStack(),
            $this->getLocaleProvider(),
            $this->generateOrderCustomerRepo($orderCutomerConfig),
            $this->createMock(LoggerInterface::class),
            true
        );

        /** @var RequestDataBag&MockObject */
        $dataBag = $this->createMock(RequestDataBag::class);

        /** @var SalesChannelContext&MockObject */
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $paymentMethod->pay(
            $this->generateTransaction($configTransaction),
            $dataBag,
            $salesChannelContext
        );

        $this->assertEquals(
            ShippingIndicator::SHIP_TO_VERIFIED_ADDRESS,
            $request->merchant_risk_statement->shipping_indicator
        );
    }
}
