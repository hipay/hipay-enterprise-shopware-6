<?php

namespace HiPay\Payment\Service;

use HiPay\Fullservice\Gateway\Client\GatewayClient;
use HiPay\Fullservice\HTTP\Configuration\Configuration;
use HiPay\Fullservice\HTTP\SimpleHTTPClient;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;

class HiPayHttpClientService
{
    /* configuration keys */
    public const API_USERNAME = 'apiUsername';
    public const API_PASSWORD = 'apiPassword';
    public const API_ENV = 'apiEnv';

    public function __construct(
        private ReadHipayConfigService $config
    ) {}

    /**
     * Create a SimpleHTTPClient with configuration.
     *
     * @param string[]|Configuration $config the configuration
     *
     * @see https://github.com/hipay/hipay-fullservice-sdk-php/blob/master/lib/HiPay/Fullservice/HTTP/Configuration/Configuration.php#L164 when array
     */
    public function getClient($config): GatewayClient
    {
        if (is_array($config)) {
            $config = new Configuration($config);
        }
        $config->setHostedPageV2(true);

        return new GatewayClient(new SimpleHTTPClient($config));
    }

    /**
     * Create a configured SimpleHttpClient.
     *
     * @param bool $isApplePay
     *
     * @throws InvalidSettingValueException
     */
    public function getConfiguredClient($isApplePay = false): GatewayClient
    {
        if ($isApplePay) {
            return $this->getClient([
                static::API_USERNAME => $this->config->getPrivateApplePayLogin(),
                static::API_PASSWORD => $this->config->getPrivateApplePayPassword(),
                static::API_ENV => strtolower($this->config->getEnvironment()),
            ]);
        } else {
            return $this->getClient([
                static::API_USERNAME => $this->config->getPrivateLogin(),
                static::API_PASSWORD => $this->config->getPrivatePassword(),
                static::API_ENV => strtolower($this->config->getEnvironment()),
            ]);
        }
    }
}
