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
use HiPay\Payment\Logger\HipayLogger;
use HiPay\Payment\Service\HiPayHttpClientService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

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
            $this->createMock(HipayLogger::class)
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
            $this->createMock(HipayLogger::class)
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
            $this->createMock(HipayLogger::class)
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
            $this->createMock(HipayLogger::class)
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

        /** @var HipayOrderEntity&MockObject */
        $hipayOrderEntity = $this->createMock(HipayOrderEntity::class);
        $hipayOrderEntity->method('getCapturesToArray')->willReturn($captures);
        $hipayOrderEntity->method('getCapturedAmount')->willReturn(10.00);

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
            $this->createMock(HipayLogger::class)
        );

        $jsonResponse = json_decode(
            $service->capture(
                $this->generateCaptureDataBag(),
                $this->generateOperationClientService($response))
            ->getContent()
        );

        $this->assertEquals(10, $captures[0]['amount']);
        $this->assertEquals(CaptureStatus::OPEN, $captures[0]['status']);

        $this->assertTrue($jsonResponse->success);
        $this->assertCount(
            1,
            array_filter($jsonResponse->captures, function ($capture) {
                return 10 === $capture->amount && CaptureStatus::OPEN === $capture->status;
            })
        );
        $this->assertEquals(20, $jsonResponse->captured_amount);
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
            $this->createMock(HipayLogger::class)
        );

        $jsonResponse = json_decode(
            $service->capture(
                $this->generateCaptureDataBag(['ok' => 'ok']),
                $this->generateOperationClientService($response))
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

        /** @var HipayOrderEntity&MockObject */
        $hipayOrderEntity = $this->createMock(HipayOrderEntity::class);
        $hipayOrderEntity->method('getRefundsToArray')->willReturn($refunds);
        $hipayOrderEntity->method('getRefundedAmount')->willReturn(10.00);

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
            $this->createMock(HipayLogger::class)
        );

        $jsonResponse = json_decode(
            $service->refund(
                $this->generateRefundDataBag(),
                $this->generateOperationClientService($response))
            ->getContent()
        );

        $this->assertEquals(5, $refunds[0]['amount']);
        $this->assertEquals(RefundStatus::OPEN, $refunds[0]['status']);

        $this->assertTrue($jsonResponse->success);
        $this->assertCount(
            1,
            array_filter($jsonResponse->refunds, function ($refund) {
                return 5 === $refund->amount && RefundStatus::OPEN === $refund->status;
            })
        );
        $this->assertEquals(15, $jsonResponse->refunded_amount);
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
            $this->createMock(HipayLogger::class)
        );

        $jsonResponse = json_decode(
            $service->refund(
                $this->generateRefundDataBag(['ok' => 'ok']),
                $this->generateOperationClientService($response))
            ->getContent()
        );

        $this->assertFalse($jsonResponse->success);
    }
}
