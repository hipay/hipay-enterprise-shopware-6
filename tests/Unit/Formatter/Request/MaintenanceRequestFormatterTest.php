<?php

namespace HiPay\Payment\Tests\Unit\Formatter\Request;

use HiPay\Payment\Formatter\Request\MaintenanceRequestFormatter;
use HiPay\Payment\HiPayPaymentPlugin;
use PHPUnit\Framework\TestCase;

class MaintenanceRequestFormatterTest extends TestCase
{
    public function testMinimalMaintenanceRequest()
    {
        $service = new MaintenanceRequestFormatter();

        $maintenance = $service->makeRequest([]);

        $this->assertSame(null, $maintenance->amount);
        $this->assertSame(null, $maintenance->operation);
        $this->assertMatchesRegularExpression('/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i', $maintenance->operation_id);
        $this->assertSame(
            json_encode([
                'source' => 'CMS',
                'brand' => 'shopware',
                'brand_version' => HiPayPaymentPlugin::getShopwareVersion(),
                'integration_version' => HiPayPaymentPlugin::getModuleVersion(),
            ]),
            $maintenance->source
        );
        $this->assertSame(null, $maintenance->basket);
    }

    public function testMaximalMaintenanceRequest()
    {
        $service = new MaintenanceRequestFormatter();

        $maintenance = $service->makeRequest([
            'amount' => 'AMOUNT',
            'operation' => 'OPERATION',
            'basket' => ['basket_id' => 'ID'],
         ]);

        $this->assertSame('AMOUNT', $maintenance->amount);
        $this->assertSame('OPERATION', $maintenance->operation);
        $this->assertMatchesRegularExpression('/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i', $maintenance->operation_id);
        $this->assertSame(
            json_encode([
                'source' => 'CMS',
                'brand' => 'shopware',
                'brand_version' => HiPayPaymentPlugin::getShopwareVersion(),
                'integration_version' => HiPayPaymentPlugin::getModuleVersion(),
            ]),
            $maintenance->source
        );
        $this->assertSame(json_encode(['basket_id' => 'ID']), $maintenance->basket);
    }
}
