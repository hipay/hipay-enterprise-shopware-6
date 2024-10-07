<?php

namespace HiPay\Payment\Subscriber;

use HiPay\Payment\PaymentMethod\PaymentMethodInterface;
use HiPay\Payment\Service\HipayAvailablePaymentProducts;
use HiPay\Payment\Service\ReadHipayConfigService;
use Shopware\Core\Checkout\Payment\PaymentEvents;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class PaymentMethodSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;
    private ReadHipayConfigService $config;

    public function __construct(
        RequestStack $requestStack,
        ReadHipayConfigService $config
    ) {
        $this->requestStack = $requestStack;
        $this->config = $config;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentEvents::PAYMENT_METHOD_LOADED_EVENT => 'addHipayConfig',
            StorefrontRenderEvent::class => 'onStorefrontRender',
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

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $currentRoute = $this->requestStack->getCurrentRequest()->attributes->get('_route');

        // Only proceed on checkout-related pages
        if (in_array($currentRoute, ['frontend.checkout.confirm.page', 'frontend.checkout.finish.page'])) {
            /** @var SalesChannelContext $context */
            $context = $event->getSalesChannelContext();
            // Fetch the current selected payment method
            $activePaymentMethod = $context->getPaymentMethod();
            // Check if PayPal is the active payment method
            if ($this->isPayPalPayment($activePaymentMethod)) {
                $paymentsProducts = HipayAvailablePaymentProducts::getInstance($this->config)
                    ->getAvailablePaymentProducts()[0];
                $isPayPalV2Enabled = isset($paymentsProducts['options']['provider_architecture_version'])
                    && 'v1' === $paymentsProducts['options']['provider_architecture_version']
                    && !empty($paymentsProducts['options']['payer_id']);
                $event->setParameter('isPayPalV2Enabled', $isPayPalV2Enabled);
            }
        }
    }

    /**
     * Check if the active payment method is PayPal.
     */
    private function isPayPalPayment(PaymentMethodEntity $paymentMethod): bool
    {
        return 'HiPay\\Payment\\PaymentMethod\\Paypal' === $paymentMethod->getHandlerIdentifier();
    }
}
