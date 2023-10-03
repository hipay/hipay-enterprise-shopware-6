<?php

namespace HiPay\Payment\Core\Checkout\Payment\Capture;

use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderCaptureDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'hipay_order_capture';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return OrderCaptureEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OrderCaptureCollection::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return HipayOrderDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('hipay_order_id', 'hipayOrderId', HipayOrderDefinition::class))->addFlags(new Required()),
            (new StringField('operation_id', 'operationId'))->addFlags(new Required()),
            (new FloatField('amount', 'amount'))->addFlags(new Required()),
            (new StringField('status', 'status'))->addFlags(new Required()),
            new ManyToOneAssociationField('hipayOrder', 'hipay_order_id', HipayOrderDefinition::class, 'id', false),
        ]);
    }
}
