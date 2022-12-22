<?php

namespace HiPay\Payment\Tests\Tools;

use HiPay\Fullservice\Gateway\Client\GatewayClient;
use HiPay\Fullservice\HTTP\SimpleHTTPClient;
use HiPay\Payment\Service\HiPayHttpClientService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

trait HipayHttpClientServiceMockTrait
{
    /**
     * @param string[]|callable[] $responses [<GatewayClient method> => <return expected>]
     *
     * @return HiPayHttpClientService&MockObject
     */
    protected function getClientService($responses = [])
    {
        if (!$this instanceof TestCase) {
            throw new \Exception('The class '.static::class.' must extends '.TestCase::class);
        }

        /** @var SimpleHTTPClient&MockObject */
        $client = $this->createMock(GatewayClient::class);

        foreach ($responses as $method => $response) {
            if (is_callable($response)) {
                $client->method($method)->willReturnCallback($response);
            } else {
                $client->method($method)->willReturn($response);
            }
        }

        /** @var HiPayHttpClientService&MockObject */
        $clientService = $this->createMock(HiPayHttpClientService::class);
        $clientService->method('getConfiguredClient')->willReturn($client);
        $clientService->method('getClient')->willReturn($client);

        return $clientService;
    }
}
