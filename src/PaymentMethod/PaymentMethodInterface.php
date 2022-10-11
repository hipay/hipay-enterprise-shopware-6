<?php

namespace HiPay\Payment\PaymentMethod;

interface PaymentMethodInterface
{
    /**
     * Name of the payment method.
     */
    public static function getName(string $lang): ?string;

    /**
     * Description of the payment method.
     */
    public static function getDescription(string $lang): ?string;

    /**
     * Add default custum fields on the plugin install.
     *
     * @return array<string,mixed>
     */
    public static function addDefaultCustomFields(): array;
}
