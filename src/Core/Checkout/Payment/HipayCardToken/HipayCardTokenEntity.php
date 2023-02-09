<?php

namespace HiPay\Payment\Core\Checkout\Payment\HipayCardToken;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class HipayCardTokenEntity extends Entity
{
    use EntityIdTrait;

    protected string $token;

    protected string $brand;

    protected string $pan;

    protected string $cardHolder;

    protected string $cardExpiryMonth;

    protected string $cardExpiryYear;

    protected string $issuer;

    protected string $country;

    protected string $customerId;

    protected CustomerEntity $customer;

    /**
     * Get the value of token.
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Set the value of token.
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Get the value of brand.
     */
    public function getBrand(): string
    {
        return $this->brand;
    }

    /**
     * Set the value of brand.
     */
    public function setBrand(string $brand): void
    {
        $this->brand = $brand;
    }

    /**
     * Get the value of pan.
     */
    public function getPan(): string
    {
        return $this->pan;
    }

    /**
     * Set the value of pan.
     */
    public function setPan(string $pan): void
    {
        $this->pan = $pan;
    }

    /**
     * Get the value of cardHolder.
     */
    public function getCardHolder(): string
    {
        return $this->cardHolder;
    }

    /**
     * Set the value of cardHolder.
     */
    public function setCardHolder(string $cardHolder): void
    {
        $this->cardHolder = $cardHolder;
    }

    /**
     * Get the value of cardExpiryMonth.
     */
    public function getCardExpiryMonth(): string
    {
        return $this->cardExpiryMonth;
    }

    /**
     * Set the value of cardExpiryMonth.
     */
    public function setCardExpiryMonth(string $cardExpiryMonth): void
    {
        $this->cardExpiryMonth = $cardExpiryMonth;
    }

    /**
     * Get the value of cardExpiryYear.
     */
    public function getCardExpiryYear(): string
    {
        return $this->cardExpiryYear;
    }

    /**
     * Set the value of cardExpiryYear.
     */
    public function setCardExpiryYear(string $cardExpiryYear): void
    {
        $this->cardExpiryYear = $cardExpiryYear;
    }

    /**
     * Get the value of issuer.
     */
    public function getIssuer(): string
    {
        return $this->issuer;
    }

    /**
     * Set the value of issuer.
     */
    public function setIssuer(string $issuer): void
    {
        $this->issuer = $issuer;
    }

    /**
     * Get the value of country.
     */
    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * Set the value of country.
     */
    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    /**
     * Get the value of customerId.
     */
    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    /**
     * Set the value of customerId.
     */
    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * Get the value of customer.
     */
    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    /**
     * Set the value of customer.
     */
    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
        $this->setCustomerId($customer->getId());
    }
}
