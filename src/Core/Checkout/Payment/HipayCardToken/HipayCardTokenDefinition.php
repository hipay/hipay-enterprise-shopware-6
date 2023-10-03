<?php

namespace HiPay\Payment\Core\Checkout\Payment\HipayCardToken;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class HipayCardTokenDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'hipay_card_token';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return HipayCardTokenEntity::class;
    }

    public function getCollectionClass(): string
    {
        return HipayCardTokenCollection::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return CustomerDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('token', 'token', 64))->addFlags(new Required()),
            (new StringField('brand', 'brand', 16))->addFlags(new Required()),
            (new StringField('pan', 'pan', 16))->addFlags(new Required()),
            (new StringField('card_holder', 'cardHolder'))->addFlags(new Required()),
            (new StringField('card_expiry_month', 'cardExpiryMonth'))->addFlags(new Required()),
            (new StringField('card_expiry_year', 'cardExpiryYear'))->addFlags(new Required()),
            (new StringField('issuer', 'issuer'))->addFlags(new Required()),
            (new StringField('country', 'country', 2))->addFlags(new Required()),
            new FkField('customer_id', 'customerId', CustomerDefinition::class),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
        ]);
    }
}
