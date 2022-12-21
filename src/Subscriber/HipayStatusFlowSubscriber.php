<?php

namespace HiPay\Payment\Subscriber;

use HiPay\Fullservice\Enum\Transaction\TransactionStatus;
use HiPay\Payment\Core\Checkout\Payment\HipayStatusFlow\HipayStatusFlowEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HipayStatusFlowSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            HipayStatusFlowEntity::HIPAY_ORDER_LOADED_EVENT => 'onHipayStatusFlowLoaded',
        ];
    }

    public function onHipayStatusFlowLoaded(EntityLoadedEvent $event): void
    {
        $reflectionClass = new \ReflectionClass(TransactionStatus::class);
        $names = array_flip($reflectionClass->getConstants());

        /** @var HipayStatusFlowEntity $statusFlow */
        foreach ($event->getEntities() as $statusFlow) {
            $statusFlow->setName($names[$statusFlow->getCode()] ?? null);
        }
    }
}
