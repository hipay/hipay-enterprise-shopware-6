<?php

namespace HiPay\Payment\Tests\Unit\Service;

use HiPay\Payment\HiPayPaymentPlugin;
use HiPay\Payment\Service\ReadHipayConfigService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ReadHipayConfigServiceTest extends TestCase
{
    private function generateService(array $params)
    {
        $fullPathParams = [];
        foreach ($params as $key => &$value) {
            $fullPathParams[HiPayPaymentPlugin::getModuleName().'.config.'.$key] = $value;
        }

        /** @var SystemConfigService&MockObject */
        $systemConfigService = $this->getMockBuilder(SystemConfigService::class)
            ->disableOriginalConstructor()
            ->getMock();

        foreach (['get', 'getString', 'getBool', 'getInt'] as $method) {
            $systemConfigService->method($method)->willReturnCallback(
                function ($key) use ($fullPathParams) {
                    return $fullPathParams[$key];
                }
            );
        }

        return new ReadHipayConfigService($systemConfigService);
    }

    public function testGetEnvironmentStage()
    {
        $environement = 'stage';
        $mock = $this->generateService(['environment' => $environement]);

        $this->assertSame(
            $environement,
            $mock->getEnvironment()
        );

        $this->assertFalse(
            $mock->isProdActivated()
        );

        $this->assertTrue(
            $mock->isTestActivated()
        );
    }

    public function testGetEnvironmentProduction()
    {
        $environement = 'production';
        $mock = $this->generateService(['environment' => $environement]);

        $this->assertSame(
            $environement,
            $mock->getEnvironment()
        );

        $this->assertTrue(
            $mock->isProdActivated()
        );

        $this->assertFalse(
            $mock->isTestActivated()
        );
    }

    public static function provideCredentials()
    {
        return [
            [
                [
                    'privateLoginStage' => 'privateLoginStage',
                    'privatePasswordStage' => 'privatePasswordStage',
                    'publicLoginStage' => 'publicLoginStage',
                    'publicPasswordStage' => 'publicPasswordStage',
                    'hashStage' => 'hashStage',
                    'passphraseStage' => 'passphraseStage',
                    'privateLoginProduction' => 'privateLoginProduction',
                    'privatePasswordProduction' => 'privatePasswordProduction',
                    'publicLoginProduction' => 'publicLoginProduction',
                    'publicPasswordProduction' => 'publicPasswordProduction',
                    'hashProduction' => 'hashProduction',
                    'passphraseProduction' => 'passphraseProduction',
                ],
            ],
        ];
    }

    #[DataProvider('provideCredentials')]
    public function testGetStageCredentials($config)
    {
        $mock = $this->generateService($config + ['environment' => 'stage']);

        $this->assertSame(
            $config['privateLoginStage'],
            $mock->getPrivateLogin()
        );

        $this->assertSame(
            $config['privatePasswordStage'],
            $mock->getPrivatePassword()
        );

        $this->assertSame(
            $config['publicLoginStage'],
            $mock->getPublicLogin()
        );

        $this->assertSame(
            $config['publicPasswordStage'],
            $mock->getPublicPassword()
        );

        $this->assertSame(
            $config['hashStage'],
            $mock->getHash()
        );

        $this->assertSame(
            $config['passphraseStage'],
            $mock->getPassphrase()
        );
    }

    #[DataProvider('provideCredentials')]
    public function testGetProductionCredentials($config)
    {
        $mock = $this->generateService($config + ['environment' => 'production']);

        $this->assertSame(
            $config['privateLoginProduction'],
            $mock->getPrivateLogin()
        );

        $this->assertSame(
            $config['privatePasswordProduction'],
            $mock->getPrivatePassword()
        );

        $this->assertSame(
            $config['publicLoginProduction'],
            $mock->getPublicLogin()
        );

        $this->assertSame(
            $config['publicPasswordProduction'],
            $mock->getPublicPassword()
        );

        $this->assertSame(
            $config['hashProduction'],
            $mock->getHash()
        );

        $this->assertSame(
            $config['passphraseProduction'],
            $mock->getPassphrase()
        );
    }

    public function testCaptureModeAuto()
    {
        $captureMode = 'auto';
        $mock = $this->generateService(['captureMode' => $captureMode]);

        $this->assertSame(
            $captureMode,
            $mock->getCaptureMode()
        );

        $this->assertFalse(
            $mock->isCaptureManual()
        );

        $this->assertTrue(
            $mock->isCaptureAuto()
        );
    }

    public function testCaptureModeManual()
    {
        $captureMode = 'manual';
        $mock = $this->generateService(['captureMode' => $captureMode]);

        $this->assertSame(
            $captureMode,
            $mock->getCaptureMode()
        );

        $this->assertTrue(
            $mock->isCaptureManual()
        );

        $this->assertFalse(
            $mock->isCaptureAuto()
        );
    }

    public function testOperationModeHostedPage()
    {
        $operationMode = 'hostedPage';
        $mock = $this->generateService(['operationMode' => $operationMode]);

        $this->assertSame(
            $operationMode,
            $mock->getOperationMode()
        );

        $this->assertTrue(
            $mock->isHostedPage()
        );

        $this->assertFalse(
            $mock->isHostedFields()
        );
    }

    public function testOperationModeHostedFields()
    {
        $operationMode = 'hostedFields';
        $mock = $this->generateService(['operationMode' => $operationMode]);

        $this->assertSame(
            $operationMode,
            $mock->getOperationMode()
        );

        $this->assertFalse(
            $mock->isHostedPage()
        );

        $this->assertTrue(
            $mock->isHostedFields()
        );
    }

    public function testIsOneClickPayment()
    {
        $oneClickPayment = 0 === random_int(0, 1);
        $mock = $this->generateService(['oneClickPayment' => $oneClickPayment]);

        $this->assertEquals(
            $oneClickPayment,
            $mock->isOneClickPayment()
        );
    }

    public function testIsCancelButtonDisplayed()
    {
        $cancelButton = 0 === random_int(0, 1);
        $mock = $this->generateService(['cancelButton' => $cancelButton]);

        $this->assertEquals(
            $cancelButton,
            $mock->isCancelButtonDisplayed()
        );
    }

    public function testIsRememberCart()
    {
        $rembemberCart = 0 === random_int(0, 1);
        $mock = $this->generateService(['rememberCart' => $rembemberCart]);

        $this->assertEquals(
            $rembemberCart,
            $mock->isRememberCart()
        );
    }

    public function testIsDebugMode()
    {
        $debugMode = 0 === random_int(0, 1);
        $mock = $this->generateService(['debugMode' => $debugMode]);

        $this->assertEquals(
            $debugMode,
            $mock->isDebugMode()
        );
    }

    public static function provideTestGet3DSAuthenticator()
    {
        return [
            [null, 0],
            [1, 1],
            [2, 2],
        ];
    }

    #[DataProvider('provideTestGet3DSAuthenticator')]
    public function testGet3DSAuthenticator($auth3DS, $expected)
    {
        $mock = $this->generateService(['authFlag3DS' => $auth3DS]);

        $this->assertEquals(
            $expected,
            $mock->get3DSAuthenticator()
        );
    }

    public function testGetCustomStyleHostedFields()
    {
        $config = [
            'hostedFieldsTextColor' => 'hostedFieldsTextColor',
            'hostedFieldsFontFamilly' => 'hostedFieldsFontFamilly',
            'hostedFieldsFontSize' => 'hostedFieldsFontSize',
            'hostedFieldsFontWeight' => 'hostedFieldsFontWeight',
            'hostedFieldsPlaceholderColor' => 'hostedFieldsPlaceholderColor',
            'hostedFieldsCaretColor' => 'hostedFieldsCaretColor',
            'hostedFieldsIconColor' => 'hostedFieldsIconColor',
            'operationMode' => 'hostedFields',
        ];

        $mock = $this->generateService($config);

        unset($config['operationMode']);

        $this->assertEquals(
            $config,
            $mock->getCustomStyle()
        );
    }

    public function testGetCustomStyleHostedPage()
    {
        $config = [
            'hostedFieldsTextColor' => 'hostedFieldsTextColor',
            'hostedFieldsFontFamilly' => 'hostedFieldsFontFamilly',
            'hostedFieldsFontSize' => 'hostedFieldsFontSize',
            'hostedFieldsFontWeight' => 'hostedFieldsFontWeight',
            'hostedFieldsPlaceholderColor' => 'hostedFieldsPlaceholderColor',
            'hostedFieldsCaretColor' => 'hostedFieldsCaretColor',
            'hostedFieldsIconColor' => 'hostedFieldsIconColor',
            'operationMode' => 'hostedPage',
        ];

        $mock = $this->generateService($config);

        unset($config['operationMode']);

        $this->assertEmpty($mock->getCustomStyle());
    }
}
