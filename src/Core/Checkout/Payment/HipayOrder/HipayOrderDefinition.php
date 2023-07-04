<?php

namespace HiPay\Payment\Core\Checkout\Payment\HipayOrder;

use HiPay\Payment\Core\Checkout\Payment\Capture\OrderCaptureDefinition;
use HiPay\Payment\Core\Checkout\Payment\HipayStatusFlow\HipayStatusFlowDefinition;
use HiPay\Payment\Core\Checkout\Payment\Refund\OrderRefundDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

// Remove Transaction relation
class HipayOrderDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'hipay_order';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return HipayOrderEntity::class;
    }

    public function getCollectionClass(): string
    {
        return HipayOrderCollection::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return OrderDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('transaction_reference', 'transactionReference'))->addFlags(new Required()),
            (new FloatField('captured_amount', 'capturedAmount'))->addFlags(new Runtime()),
            (new FloatField('refunded_amount', 'refundedAmount'))->addFlags(new Runtime()),
            (new FloatField('captured_amount_in_progress', 'capturedAmountInProgress'))->addFlags(new Runtime()),
            (new FloatField('refunded_amount_in_progress', 'refundedAmountInProgress'))->addFlags(new Runtime()),
            (new OneToManyAssociationField('captures', OrderCaptureDefinition::class, 'hipay_order_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('refunds', OrderRefundDefinition::class, 'hipay_order_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('statusFlows', HipayStatusFlowDefinition::class, 'hipay_order_id'))->addFlags(new CascadeDelete()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required()),
            (new FkField('transaction_id', 'transactionId', OrderTransactionDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderTransactionDefinition::class, 'transaction_version_id'))->addFlags(new Required()),
            new OneToOneAssociationField('order', 'order_id', 'id', OrderDefinition::class, false),
            new OneToOneAssociationField('transaction', 'transaction_id', 'id', OrderTransactionDefinition::class, false),
        ]);
    }
}
