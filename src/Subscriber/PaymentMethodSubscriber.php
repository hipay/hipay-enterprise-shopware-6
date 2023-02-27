<?php

namespace HiPay\Payment\Subscriber;

use HiPay\Payment\PaymentMethod\PaymentMethodInterface;
use Shopware\Core\Checkout\Payment\PaymentEvents;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentMethodSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PaymentEvents::PAYMENT_METHOD_LOADED_EVENT => 'addHipayConfig',
        ];
    }

    public function addHipayConfig(EntityLoadedEvent $event): void
    {
        /** @var PaymentMethodEntity $method */
        foreach ($event->getEntities() as $method) {
            /** @var class-string $classname */
            $classname = $method->getHandlerIdentifier();
            if (is_a($classname, PaymentMethodInterface::class, true)) {
                $method->addExtension('hipayConfig', new ArrayEntity($classname::getConfig()));
            }
        }
    }
}
