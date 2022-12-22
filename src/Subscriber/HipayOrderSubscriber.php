<?php

namespace HiPay\Payment\Subscriber;

use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HipayOrderSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            HipayOrderEntity::HIPAY_ORDER_LOADED_EVENT => 'onHipayOrderLoaded',
        ];
    }

    public function onHipayOrderLoaded(EntityLoadedEvent $event): void
    {
        /** @var HipayOrderEntity $hipayOrderEntity */
        foreach ($event->getEntities() as $hipayOrderEntity) {
            $captures = $hipayOrderEntity->getCaptures();
            $refunds = $hipayOrderEntity->getRefunds();

            $hipayOrderEntity->setCapturedAmount($captures->calculCapturedAmount());
            $hipayOrderEntity->setCapturedAmountInProgress($captures->calculCapturedAmountInProgress());
            $hipayOrderEntity->setRefundedAmount($refunds->calculRefundedAmount());
            $hipayOrderEntity->setRefundedAmountInProgress($refunds->calculRefundedAmountInProgress());
        }
    }
}
