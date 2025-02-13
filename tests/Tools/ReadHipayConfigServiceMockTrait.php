<?php

namespace HiPay\Payment\Tests\Tools;

use HiPay\Payment\HiPayPaymentPlugin;
use HiPay\Payment\Service\ReadHipayConfigService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Util\Exception;
use Shopware\Core\System\SystemConfig\SystemConfigService;

trait ReadHipayConfigServiceMockTrait
{
    /**
     * @param array $params [<key> => <value>]
     */
    protected function getReadHipayConfig($params = [])
    {
        if (!$this instanceof TestCase) {
            throw new Exception('The class '.static::class.' must extends '.TestCase::class);
        }

        $fullPathParams = [];
        foreach ($params as $key => $value) {
            $fullPathParams[HiPayPaymentPlugin::getModuleName().'.config.'.$key] = $value;
        }

        /** @var SystemConfigService&MockObject */
        $systemConfigService = $this->getMockBuilder(SystemConfigService::class)
            ->disableOriginalConstructor()
            ->getMock();

        foreach (['get', 'getString', 'getBool', 'getInt'] as $method) {
            $systemConfigService->method($method)->willReturnCallback(
                function ($key) use ($fullPathParams) {
                    if(!isset($fullPathParams[$key])) {
                        return null;
                    }
                    return $fullPathParams[$key];
                }
            );
        }

        return new ReadHipayConfigService($systemConfigService);
    }
}
