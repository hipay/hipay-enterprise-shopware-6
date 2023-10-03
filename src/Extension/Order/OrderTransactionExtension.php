<?php

namespace HiPay\Payment\Extension\Order;

use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderTransactionExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField('hipayOrder', 'id', 'transaction_id', HipayOrderDefinition::class, false),
        );
    }

    public function getDefinitionClass(): string
    {
        return OrderTransactionDefinition::class;
    }
}
