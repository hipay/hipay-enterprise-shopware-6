<?php

namespace HiPay\Payment\PaymentMethod;

use Symfony\Component\DependencyInjection\ContainerInterface;

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
     * Add default custom fields on the plugin install.
     *
     * @return array<string,mixed>
     */
    public static function addDefaultCustomFields(): array;

    /**
     * Image of the payment method.
     */
    public static function getImage(): ?string;

    /**
     * Specific rule of the payment method.
     *
     * @return array<string,mixed>
     */
    public static function getRule(ContainerInterface $container): ?array;

    /**
     * Return the initial position of the payment method.
     */
    public static function getPosition(): int;
}
