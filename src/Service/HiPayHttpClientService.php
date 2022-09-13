<?php

namespace HiPay\Payment\Service;

use HiPay\Fullservice\HTTP\Client;
use HiPay\Fullservice\HTTP\Configuration\Configuration;
use HiPay\Fullservice\HTTP\SimpleHTTPClient;

class HiPayHttpClientService
{
    public const URL_SECURITY_SETTINGS = 'v2/security-settings';

    /**
     * Create a SimpleHTTPClient with connfiguration.
     *
     * @param string[]|Configuration $config the configuration
     *
     * @see https://github.com/hipay/hipay-fullservice-sdk-php/blob/master/lib/HiPay/Fullservice/HTTP/Configuration/Configuration.php#L164 when array
     */
    public function getClient($config): Client
    {
        if (is_array($config)) {
            $config = new Configuration($config);
        }

        return new SimpleHTTPClient($config);
    }
}
