<?php

namespace HiPay\Payment\Tests\Unit\Controller;

use HiPay\Fullservice\Gateway\Client\GatewayClient;
use HiPay\Fullservice\Gateway\Model\Operation;
use HiPay\Fullservice\Gateway\Model\SecuritySettings;
use HiPay\Fullservice\Gateway\Request\Maintenance\MaintenanceRequest;
use HiPay\Payment\Controller\AdminController;
use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderCollection;
use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderEntity;
use HiPay\Payment\Enum\CaptureStatus;
use HiPay\Payment\Enum\RefundStatus;
use HiPay\Payment\Formatter\Request\MaintenanceRequestFormatter;
use HiPay\Payment\HiPayPaymentPlugin;
use HiPay\Payment\Service\HiPayHttpClientService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AdminControllerTest extends TestCase
{
    private function generateRequestDataBag(array $params = [], string $env = 'Production')
    {
        if (!isset($params['environment'])) {
            $params = ['environment' => $env];
        }

        foreach (['public', 'private'] as $scope) {
            foreach (['Login', 'Password'] as $field) {
                $key = HiPayPaymentPlugin::getModuleName().'.config.'.$scope.$field.$env;
                if (!isset($params[$key])) {
                    $params[$key] = $key;
                }
            }
        }

        /** @var RequestDataBag&MockObject */
        $bag = $this->createMock(RequestDataBag::class);
        foreach (['get', 'getAlpha'] as $method) {
            $bag->method($method)->willReturnCallback(
                function ($key, $default = null) use ($params) {
                    return $params[$key] ?? $default;
                }
            );
        }

        return $bag;
    }

    private function generateClientService(array $responses)
    {
        $clients = [];

        foreach ($responses as $response) {
            /** @var GatewayClient&MockObject */
            $client = $this->createMock(GatewayClient::class);
            if ($response instanceof \Throwable) {
                $client->method('requestSecuritySettings')->willThrowException($response);
            } else {
                $client->method('requestSecuritySettings')->willReturn($response);
            }

            $clients[] = $client;
        }

        /** @var HiPayHttpClientService&MockObject */
        $clientService = $this->createMock(HiPayHttpClientService::class);
        $clientService->method('getClient')->willReturnOnConsecutiveCalls(...$clients);

        return $clientService;
    }

    public function testCheckAccessValid()
    {
        $responses = [
            new SecuritySettings(''),
            new SecuritySettings(''),
        ];

        $service = new AdminController(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class)
        );

        $jsonResponse = json_decode(
            $service->checkAccess(
                $this->generateRequestDataBag(),
                $this->generateClientService($responses)
            )->getContent(),
            true
        );

        $this->assertSame(
            ['success' => true, 'message' => 'Access granted'],
            $jsonResponse
        );
    }

    public function testCheckAccessInvalidPublic()
    {
        $responses = [
            new \Exception('Foo'),
            new SecuritySettings(''),
        ];

        $service = new AdminController(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class)
        );

        $jsonResponse = json_decode(
            $service->checkAccess(
                $this->generateRequestDataBag(),
                $this->generateClientService($responses)
            )->getContent(),
            true
        );

        $this->assertSame(
            ['success' => false, 'message' => 'Error on public key : Foo'],
            $jsonResponse
        );
    }

    public function testCheckAccessInvalidPrivate()
    {
        $responses = [
            new SecuritySettings(''),
            new \Exception('Bar'),
        ];

        $service = new AdminController(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class)
        );

        $jsonResponse = json_decode(
            $service->checkAccess(
                $this->generateRequestDataBag(),
                $this->generateClientService($responses)
            )->getContent(),
            true
        );

        $this->assertSame(
            ['success' => false, 'message' => 'Error on private key : Bar'],
            $jsonResponse
        );
    }

    public function testCheckAccessInvalidConfig()
    {
        $responses = [null];

        $service = new AdminController(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class)
        );

        $jsonResponse = json_decode(
            $service->checkAccess(
                $this->generateRequestDataBag([], 'Foobar'),
                $this->generateClientService($responses)
            )->getContent()
        );

        $this->assertFalse($jsonResponse->success);
    }

    private function generateCaptureDataBag($params = [])
    {
        if (empty($params)) {
            $params['hipayOrder'] = json_encode((object) ['id' => 'ID']);
            $params['amount'] = '10';
        }

        /** @var RequestDataBag&MockObject */
        $bag = $this->createMock(RequestDataBag::class);
        foreach (['get'] as $method) {
            $bag->method($method)->willReturnCallback(
                function ($key, $default = null) use ($params) {
                    return $params[$key] ?? $default;
                }
            );
        }

        return $bag;
    }

    private function generateRefundDataBag($params = [])
    {
        if (empty($params)) {
            $params['hipayOrder'] = json_encode((object) ['id' => 'ID']);
            $params['amount'] = '5';
        }

        /** @var RequestDataBag&MockObject */
        $bag = $this->createMock(RequestDataBag::class);
        foreach (['get'] as $method) {
            $bag->method($method)->willReturnCallback(
                function ($key, $default = null) use ($params) {
                    return $params[$key] ?? $default;
                }
            );
        }

        return $bag;
    }

    private function generateOperationClientService($response)
    {
        /** @var GatewayClient&MockObject */
        $client = $this->createMock(GatewayClient::class);
        if ($response instanceof \Throwable) {
            $client->method('requestMaintenanceOperation')->willThrowException($response);
        } else {
            $client->method('requestMaintenanceOperation')->willReturn($response);
        }

        /** @var HiPayHttpClientService&MockObject */
        $clientService = $this->createMock(HiPayHttpClientService::class);
        $clientService->method('getClient')->willReturn($client);

        return $clientService;
    }

    public function testValidCapture()
    {
        $response = new Operation(
            'mid',
            'authCode',
            'trxRef',
            'dateCreated',
            'dateUpdated',
            'dateAuth',
            'status',
            'state',
            'message',
            'authAmount',
            'capturedAmount',
            'refunedAmount',
            'decimals',
            'currency',
            'operation'
        );

        $request = new MaintenanceRequest();
        /** @var MaintenanceRequestFormatter&MockObject */
        $maintenanceRequestFormatter = $this->createMock(MaintenanceRequestFormatter::class);
        $maintenanceRequestFormatter->method('makeRequest')->willReturn($request);

        $captures = [];

        /** @var PaymentMethodEntity&MockObject */
        $paymentMethod = $this->createMock(PaymentMethodEntity::class);
        $paymentMethod->method('getExtension')->willReturn(new ArrayEntity(['allowPartialCapture' => true]));

        $amount = new CalculatedPrice(
            10.0,
            10.0,
            $this->createMock(CalculatedTaxCollection::class),
            $this->createMock(TaxRuleCollection::class),
        );

        /** @var OrderTransactionEntity&MockObject */
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getPaymentMethod')->willReturn($paymentMethod);
        $transaction->method('getAmount')->willReturn($amount);

        /** @var HipayOrderEntity&MockObject */
        $hipayOrderEntity = $this->createMock(HipayOrderEntity::class);
        $hipayOrderEntity->method('getCapturesToArray')->willReturn($captures);
        $hipayOrderEntity->method('getCapturedAmount')->willReturn(10.00);
        $hipayOrderEntity->method('getTransaction')->willReturn($transaction);

        /** @var EntityRepository&MockObject */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderCollection = new HipayOrderCollection([$hipayOrderEntity]);

        /** @var Criteria|null */
        $hipayOrderCriteria = null;
        $hipayOrderRepo->method('search')->willReturnCallback(function ($crit, $context) use (&$hipayOrderCriteria, $hipayOrderCollection) {
            $hipayOrderCriteria = $crit;

            return new EntitySearchResult(HipayOrderEntity::class, $hipayOrderCollection->count(), $hipayOrderCollection, null, $hipayOrderCriteria, $context);
        });

        /** @var EntityRepository&MockObject */
        $hipayOrderCaptureRepo = $this->createMock(EntityRepository::class);

        $hipayOrderCaptureRepo->method('create')->willReturnCallback(function ($args) use (&$captures) {
            $captures = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $service = new AdminController(
            $hipayOrderRepo,
            $hipayOrderCaptureRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class)
        );

        $context = $this->createMock(SalesChannelContext::class);
        $jsonResponse = json_decode(
            $service->capture(
                $this->generateCaptureDataBag(),
                $this->generateOperationClientService($response),
                $context
            )
                ->getContent()
        );

        $this->assertEquals(10, $captures[0]['amount']);
        $this->assertEquals(CaptureStatus::OPEN, $captures[0]['status']);

        $this->assertTrue($jsonResponse->success);
    }

    public function testInvalidCapture()
    {
        $response = null;

        $request = new MaintenanceRequest();
        /** @var MaintenanceRequestFormatter&MockObject */
        $maintenanceRequestFormatter = $this->createMock(MaintenanceRequestFormatter::class);
        $maintenanceRequestFormatter->method('makeRequest')->willReturn($request);

        $service = new AdminController(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class)
        );

        $context = $this->createMock(SalesChannelContext::class);
        $jsonResponse = json_decode(
            $service->capture(
                $this->generateCaptureDataBag(['ok' => 'ok']),
                $this->generateOperationClientService($response),
                $context
            )
                ->getContent()
        );

        $this->assertFalse($jsonResponse->success);
    }

    public function testValidRefund()
    {
        $response = new Operation(
            'mid',
            'authCode',
            'trxRef',
            'dateCreated',
            'dateUpdated',
            'dateAuth',
            'status',
            'state',
            'message',
            'authAmount',
            'capturedAmount',
            'refunedAmount',
            'decimals',
            'currency',
            'operation'
        );

        $request = new MaintenanceRequest();
        /** @var MaintenanceRequestFormatter&MockObject */
        $maintenanceRequestFormatter = $this->createMock(MaintenanceRequestFormatter::class);
        $maintenanceRequestFormatter->method('makeRequest')->willReturn($request);

        $refunds = [];

        /** @var PaymentMethodEntity&MockObject */
        $paymentMethod = $this->createMock(PaymentMethodEntity::class);
        $paymentMethod->method('getExtension')->willReturn(new ArrayEntity(['allowPartialCapture' => true]));

        /** @var OrderTransactionEntity&MockObject */
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getPaymentMethod')->willReturn($paymentMethod);

        /** @var HipayOrderEntity&MockObject */
        $hipayOrderEntity = $this->createMock(HipayOrderEntity::class);
        $hipayOrderEntity->method('getRefundsToArray')->willReturn($refunds);
        $hipayOrderEntity->method('getRefundedAmount')->willReturn(10.00);
        $hipayOrderEntity->method('getTransaction')->willReturn($transaction);

        /** @var EntityRepository&MockObject */
        $hipayOrderRepo = $this->createMock(EntityRepository::class);
        $hipayOrderCollection = new HipayOrderCollection([$hipayOrderEntity]);

        /** @var Criteria|null */
        $hipayOrderCriteria = null;
        $hipayOrderRepo->method('search')->willReturnCallback(function ($crit, $context) use (&$hipayOrderCriteria, $hipayOrderCollection) {
            $hipayOrderCriteria = $crit;

            return new EntitySearchResult(HipayOrderEntity::class, $hipayOrderCollection->count(), $hipayOrderCollection, null, $hipayOrderCriteria, $context);
        });

        /** @var EntityRepository&MockObject */
        $hipayOrderRefundRepo = $this->createMock(EntityRepository::class);

        $hipayOrderRefundRepo->method('create')->willReturnCallback(function ($args) use (&$refunds) {
            $refunds = $args;

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $service = new AdminController(
            $hipayOrderRepo,
            $this->createMock(EntityRepository::class),
            $hipayOrderRefundRepo,
            $this->createMock(LoggerInterface::class)
        );

        $context = $this->createMock(SalesChannelContext::class);
        $jsonResponse = json_decode(
            $service->refund(
                $this->generateRefundDataBag(),
                $this->generateOperationClientService($response),
                $context
            )
                ->getContent()
        );

        $this->assertEquals(5, $refunds[0]['amount']);
        $this->assertEquals(RefundStatus::OPEN, $refunds[0]['status']);

        $this->assertTrue($jsonResponse->success);
    }

    public function testInvalidRefund()
    {
        $response = null;

        $request = new MaintenanceRequest();
        /** @var MaintenanceRequestFormatter&MockObject */
        $maintenanceRequestFormatter = $this->createMock(MaintenanceRequestFormatter::class);
        $maintenanceRequestFormatter->method('makeRequest')->willReturn($request);

        $service = new AdminController(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class)
        );

        $context = $this->createMock(SalesChannelContext::class);
        $jsonResponse = json_decode(
            $service->refund(
                $this->generateRefundDataBag(['ok' => 'ok']),
                $this->generateOperationClientService($response),
                $context
            )
                ->getContent()
        );

        $this->assertFalse($jsonResponse->success);
    }

    public function testValidCancel()
    {
        /** @var SalesChannelContext&MockObject */
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        /** @var PaymentMethodEntity&MockObject */
        $paymentMethod = $this->createMock(PaymentMethodEntity::class);
        $paymentMethod->method('getExtension')->willReturn(new ArrayEntity(['allowPartialCapture' => true]));

        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getPaymentMethod')->willReturn($paymentMethod);

        /** @var HipayOrderEntity&MockObject */
        $hipayOrderEntity = $this->createMock(HipayOrderEntity::class);
        $hipayOrderEntity->method('getTransaction')->willReturn($transaction);

        /** @var EntitySearchResult&MockObject */
        $search = $this->createMock(EntitySearchResult::class);
        $entityCollection = $this->createMock(EntityCollection::class);
        $search->method('getEntities')->willReturn($entityCollection);
        $entityCollection->method('first')->willReturn($hipayOrderEntity);

        /** @var EntityRepository&MockObject */
        $orderRepo = $this->createMock(EntityRepository::class);
        $orderRepo->method('search')->willReturn($search);

        $service = new AdminController(
            $orderRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class)
        );

        /** @var RequestDataBag&MockObject */
        $params = $this->createMock(RequestDataBag::class);
        $params->method('get')->willReturn(json_encode(['id' => 'FOO_ORDER_ID']));

        $client = $this->createMock(HiPayHttpClientService::class);
        $response = $service->cancel($params, $client, $context);

        $this->assertSame(
            ['success' => true],
            json_decode($response->getContent(), true)
        );
    }

    public function testFaileddCancel()
    {
        $service = new AdminController(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class)
        );

        /** @var RequestDataBag&MockObject */
        $params = $this->createMock(RequestDataBag::class);
        $params->method('get')->willReturn(null);

        $response = $service->cancel($params, $this->createMock(HiPayHttpClientService::class), $this->createMock(SalesChannelContext::class));

        $this->assertSame(
            ['success' => false, 'message' => 'HiPay Order parameter is mandatory'],
            json_decode($response->getContent(), true)
        );
    }
}
