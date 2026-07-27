<?php

namespace HiPay\Payment\Subscriber;

use HiPay\Payment\HiPayPaymentPlugin;
use HiPay\Payment\PaymentMethod\Alma3X;
use HiPay\Payment\PaymentMethod\Alma4X;
use HiPay\Payment\PaymentMethod\ApplePay;
use HiPay\Payment\PaymentMethod\Bancontact;
use HiPay\Payment\PaymentMethod\CreditCard;
use HiPay\Payment\PaymentMethod\Giropay;
use HiPay\Payment\PaymentMethod\Ideal;
use HiPay\Payment\PaymentMethod\Klarna;
use HiPay\Payment\PaymentMethod\Mbway;
use HiPay\Payment\PaymentMethod\Multibanco;
use HiPay\Payment\PaymentMethod\Mybank;
use HiPay\Payment\PaymentMethod\Paypal;
use HiPay\Payment\PaymentMethod\Przelewy24;
use HiPay\Payment\PaymentMethod\SepaDirectDebit;
use HiPay\Payment\PaymentMethod\Sofort;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class HipayPaymentMethodsLoadedSubscriber implements EventSubscriberInterface
{

    private const HIPAY_TEST_ENVIRONMENT = "Stage";
    private const HIPAY_LIVE_ENVIRONMENT = "Production";
    private const HIPAY_PAYMENT_METHODS = [
        Alma3X::class,
        Alma4X::class,
        ApplePay::class,
        Bancontact::class,
        CreditCard::class,
        Giropay::class,
        Ideal::class,
        Klarna::class,
        Paypal::class,
        Mbway::class,
        Multibanco::class,
        Mybank::class,
        Przelewy24::class,
        SepaDirectDebit::class,
        Sofort::class,
    ];

    public function __construct(
        private KernelPluginLoader $kernelPluginLoader,
        private SystemConfigService $systemConfigService,
        private RequestStack $requestStack)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            "payment_method.search.result.loaded" => 'onHipayPaymentMethodsLoaded',
        ];
    }

    /**
     * @param EntitySearchResultLoadedEvent<PaymentMethodCollection> $event
     */
    public function onHipayPaymentMethodsLoaded(EntitySearchResultLoadedEvent $event): void
    {
        $pluginData = $this->kernelPluginLoader->getPluginInstance('HiPay\Payment\HiPayPaymentPlugin');

        if($pluginData === null) {
            return;
        }

        if(!$pluginData->isActive()) {
            return;
        }

        $paymentMethods = $event->getResult()->getEntities();

        if($this->isAndroidDevice()) {
            foreach ($paymentMethods as $key => $paymentMethod) {
                if($paymentMethod->getHandlerIdentifier() === ApplePay::class) {
                    $paymentMethods->remove($key);
                }
            }
        }

        $env = $this->systemConfigService->get("HiPayPaymentPlugin.config.environment");
        $removeHiPayPaymentMethods = $env === null;

        if($env === self::HIPAY_LIVE_ENVIRONMENT) {
            $privateLogin = $this->systemConfigService->get('HiPayPaymentPlugin.config.privateLoginProduction');
            $privatePassword = $this->systemConfigService->get('HiPayPaymentPlugin.config.privatePasswordProduction');
            $publicLogin = $this->systemConfigService->get('HiPayPaymentPlugin.config.publicLoginProduction');
            $publicPassword = $this->systemConfigService->get('HiPayPaymentPlugin.config.publicPasswordProduction');
            $passphrase = $this->systemConfigService->get('HiPayPaymentPlugin.config.passphraseProduction');
            $hash = $this->systemConfigService->get('HiPayPaymentPlugin.config.hashProduction');
        }

        if($env === self::HIPAY_TEST_ENVIRONMENT) {
            $privateLogin = $this->systemConfigService->get('HiPayPaymentPlugin.config.privateLoginStage');
            $privatePassword = $this->systemConfigService->get('HiPayPaymentPlugin.config.privatePasswordStage');
            $publicLogin = $this->systemConfigService->get('HiPayPaymentPlugin.config.publicLoginStage');
            $publicPassword = $this->systemConfigService->get('HiPayPaymentPlugin.config.publicPasswordStage');
            $passphrase = $this->systemConfigService->get('HiPayPaymentPlugin.config.passphraseStage');
            $hash = $this->systemConfigService->get('HiPayPaymentPlugin.config.hashStage');
        }

        $removeHiPayPaymentMethods = $removeHiPayPaymentMethods ||
            empty($privateLogin) ||
            empty($privatePassword) ||
            empty($publicLogin) ||
            empty($publicPassword) ||
            empty($passphrase) ||
            empty($hash);

        if(!$removeHiPayPaymentMethods) {
            return;
        }

        foreach ($paymentMethods as $key => $paymentMethod) {
            if(in_array($paymentMethod->getHandlerIdentifier(), self::HIPAY_PAYMENT_METHODS)) {
                $paymentMethods->remove($key);
            }
        }
    }

    private function isAndroidDevice(): bool
    {
        $userAgent = $this->requestStack->getCurrentRequest()?->headers->get('User-Agent');

        return $userAgent !== null && stripos($userAgent, 'Android') !== false;
    }
}