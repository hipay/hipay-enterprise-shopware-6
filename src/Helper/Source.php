<?php

namespace HiPay\Payment\Helper;

use HiPay\Payment\HiPayPayments;

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
            'brand_version' => HiPayPayments::getShopwareVersion(),
            'integration_version' => HiPayPayments::getModuleVersion(),
        ]);
    }
}
