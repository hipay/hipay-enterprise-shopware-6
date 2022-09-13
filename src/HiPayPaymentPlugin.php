<?php

declare(strict_types=1);

namespace HiPay\Payment;

use Shopware\Core\Framework\Plugin;

class HiPayPaymentPlugin extends Plugin
{
    /**
     * Get the plugin name.
     */
    public static function getModuleName(): string
    {
        $path = explode('\\', __CLASS__);

        return array_pop($path);
    }
}
