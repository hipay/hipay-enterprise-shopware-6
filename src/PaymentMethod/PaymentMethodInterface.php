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
     * Add default custom fields on the plugin install.
     *
     * @return array<string,mixed>
     */
    public static function addDefaultCustomFields(): array;

    /**
     * Get the configuration of the payment method based on sdk json files.
     *
     * @return array<string,mixed>
     */
    public static function getConfig(): array;

    /**
     * Image of the payment method.
     */
    public static function getImage(): ?string;

    /**
     * Get the ISO currencies rules.
     *
     * @return array<string>|null
     */
    public static function getCurrencies(): ?array;

    /**
     * Get the ISO countries rules.
     *
     * @return array<string>|null
     */
    public static function getCountries(): ?array;

    /**
     * Return the initial position of the payment method.
     */
    public static function getPosition(): int;
}
