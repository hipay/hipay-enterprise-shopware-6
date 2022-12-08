<?php

namespace HiPay\Payment\Core\Checkout\Payment\HipayOrder;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                  add(HipayOrderEntity $entity)
 * @method void                  set(string $key, HipayOrderEntity $entity)
 * @method HipayOrderEntity[]    getIterator()
 * @method HipayOrderEntity[]    getElements()
 * @method HipayOrderEntity|null get(string $key)
 * @method HipayOrderEntity|null first()
 * @method HipayOrderEntity|null last()
 *
 * @extends EntityCollection<HipayOrderEntity>
 */
class HipayOrderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return HipayOrderEntity::class;
    }
}
