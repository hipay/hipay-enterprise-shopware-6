<?php

namespace HiPay\Payment\Core\Checkout\Payment\HipayCardToken;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                      add(HipayCardTokenEntity $entity)
 * @method void                      set(string $key, HipayCardTokenEntity $entity)
 * @method HipayCardTokenEntity[]    getIterator()
 * @method HipayCardTokenEntity[]    getElements()
 * @method HipayCardTokenEntity|null get(string $key)
 * @method HipayCardTokenEntity|null first()
 * @method HipayCardTokenEntity|null last()
 *
 * @extends EntityCollection<HipayCardTokenEntity>
 */
class HipayCardTokenCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return HipayCardTokenEntity::class;
    }
}
