<?php

namespace HiPay\Payment\Core\Checkout\Payment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(HipayNotificationEntity $entity)
 * @method void                         set(string $key, HipayNotificationEntity $entity)
 * @method HipayNotificationEntity[]    getIterator()
 * @method HipayNotificationEntity[]    getElements()
 * @method HipayNotificationEntity|null get(string $key)
 * @method HipayNotificationEntity|null first()
 * @method HipayNotificationEntity|null last()
 *
 * @extends EntityCollection<HipayNotificationEntity>
 */
class HipayNotificationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return HipayNotificationEntity::class;
    }
}
