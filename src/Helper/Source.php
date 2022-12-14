<?php

namespace HiPay\Payment\Helper;

use HiPay\Payment\HiPayPaymentPlugin;

class Source
{
    /**
     * Get source for HiPay API requests.
     *
     * @return string|false
     */
    public static function toString()
    {
        return json_encode([
            'source' => 'CMS',
            'brand' => 'shopware',
            'brand_version' => HiPayPaymentPlugin::getShopwareVersion(),
            'integration_version' => HiPayPaymentPlugin::getModuleVersion(),
        ]);
    }
}
