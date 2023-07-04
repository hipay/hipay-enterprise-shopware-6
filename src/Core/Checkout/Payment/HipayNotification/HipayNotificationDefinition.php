<?php

namespace HiPay\Payment\Core\Checkout\Payment\HipayNotification;

use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class HipayNotificationDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'hipay_notification';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return HipayNotificationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return HipayNotificationCollection::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return HipayOrderDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new IntField('status', 'status'))->addFlags(new Required()),
            (new JsonField('data', 'data'))->addFlags(new Required()),
            (new DateTimeField('notification_updated_at', 'notificationUpdatedAt'))->addFlags(new Required()),
            new FkField('hipay_order_id', 'hipayOrderId', HipayOrderDefinition::class),
            new ManyToOneAssociationField('hipayOrder', 'hipay_order_id', HipayOrderDefinition::class, 'id', false),
        ]);
    }
}
