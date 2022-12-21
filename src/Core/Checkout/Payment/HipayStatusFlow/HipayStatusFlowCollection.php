<?php

namespace HiPay\Payment\Core\Checkout\Payment\HipayStatusFlow;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                       add(HipayStatusFlowEntity $entity)
 * @method void                       set(string $key, HipayStatusFlowEntity $entity)
 * @method HipayStatusFlowEntity[]    getIterator()
 * @method HipayStatusFlowEntity[]    getElements()
 * @method HipayStatusFlowEntity|null get(string $key)
 * @method HipayStatusFlowEntity|null first()
 * @method HipayStatusFlowEntity|null last()
 *
 * @extends EntityCollection<HipayStatusFlowEntity>
 */
class HipayStatusFlowCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return HipayStatusFlowEntity::class;
    }
}
