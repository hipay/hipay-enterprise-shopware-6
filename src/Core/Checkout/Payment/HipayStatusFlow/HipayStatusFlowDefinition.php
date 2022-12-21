<?php

namespace HiPay\Payment\Core\Checkout\Payment\HipayStatusFlow;

use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

// Remove Transaction relation
class HipayStatusFlowDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'hipay_status_flow';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return HipayStatusFlowEntity::class;
    }

    public function getCollectionClass(): string
    {
        return HipayStatusFlowCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new IntField('code', 'code'))->addFlags(new Required()),
            (new StringField('name', 'name'))->addFlags(new Runtime()),
            new StringField('message', 'message'),
            (new FloatField('amount', 'amount'))->addFlags(new Required()),
            (new StringField('hash', 'hash'))->addFlags(new Required()),
            new ManyToOneAssociationField('hipayOrder', 'hipay_order_id', HipayOrderDefinition::class, 'id', false),
            (new FkField('hipay_order_id', 'hipayOrderId', HipayOrderDefinition::class))->addFlags(new Required()),
        ]);
    }
}
