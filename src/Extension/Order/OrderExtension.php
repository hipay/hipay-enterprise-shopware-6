<?php

namespace HiPay\Payment\Extension\Order;

use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField('hipayOrder', 'id', 'order_id', HipayOrderDefinition::class))->addFlags(new CascadeDelete()),
        );
    }

    public function getDefinitionClass(): string
    {
        return OrderDefinition::class;
    }
}
