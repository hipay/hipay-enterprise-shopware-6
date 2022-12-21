<?php

namespace HiPay\Payment\Tests\Unit\Subscriber;

use HiPay\Fullservice\Enum\Transaction\TransactionStatus;
use HiPay\Payment\Core\Checkout\Payment\HipayStatusFlow\HipayStatusFlowDefinition;
use HiPay\Payment\Core\Checkout\Payment\HipayStatusFlow\HipayStatusFlowEntity;
use HiPay\Payment\Subscriber\HipayStatusFlowSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;

class HipayStatusFlowSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents()
    {
        $this->assertSame(
            [HipayStatusFlowEntity::HIPAY_ORDER_LOADED_EVENT => 'onHipayStatusFlowLoaded'],
            HipayStatusFlowSubscriber::getSubscribedEvents()
        );
    }

    public function testOnHipayStatusFlowLoaded()
    {
        $reflectionClass = new \ReflectionClass(TransactionStatus::class);
        $consts = $reflectionClass->getConstants();

        $subscriber = new HipayStatusFlowSubscriber();

        foreach ($consts as $name => $code) {
            $entity = new HipayStatusFlowEntity();
            $entity->setCode($code);

            $event = new EntityLoadedEvent(
                new HipayStatusFlowDefinition(),
                [$entity],
                Context::createDefaultContext()
            );

            $subscriber->onHipayStatusFlowLoaded($event);

            $this->assertSame(
                $name,
                $entity->getName()
            );
        }
    }
}
