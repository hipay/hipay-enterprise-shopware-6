<?php

namespace HiPay\Payment\Subscriber;

use HiPay\Payment\Route\HipayCardToken\HipayCardTokenRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HipayCardTokenSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private HipayCardTokenRoute $hipayCardTokenRoute
    ) {}

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'addCardTokens',
            AccountEditOrderPageLoadedEvent::class => 'addCardTokens',
            AccountPaymentMethodPageLoadedEvent::class => 'addCardTokens',
        ];
    }

    public function addCardTokens(PageLoadedEvent $event): void
    {
        $cardTokenResponse = $this->hipayCardTokenRoute->load(new Criteria(), $event->getSalesChannelContext());

        $event->getPage()->addExtension('card_tokens', $cardTokenResponse->getCardTokens());
    }
}
