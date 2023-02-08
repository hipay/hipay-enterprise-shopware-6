<?php

namespace HiPay\Payment\Extension\Customer;

use HiPay\Payment\Core\Checkout\Payment\HipayCardToken\HipayCardTokenDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CustomerExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('cardTokens', HipayCardTokenDefinition::class, 'customer_id'))->addFlags(new CascadeDelete()),
        );
    }

    public function getDefinitionClass(): string
    {
        return CustomerDefinition::class;
    }
}
