<?php

declare(strict_types=1);

namespace HiPay\Payment;

use HiPay\Fullservice\Exception\UnexpectedValueException;
use HiPay\Payment\PaymentMethod\CreditCard;
use HiPay\Payment\PaymentMethod\PaymentMethodInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * @infection-ignore-all
 */
class HiPayPaymentPlugin extends Plugin
{
    /**
     * Plugin ID.
     */
    private string $pluginId;

    private const PARAMS = [
        'CAPTURE_MODE' => 'HiPayPaymentPlugin.config.captureMode',
        'HIPAY_ENVIRONMENT' => 'HiPayPaymentPlugin.config.environment',
        'OPERATION_MODE' => 'HiPayPaymentPlugin.config.hashProduction',
        'PRIVATE_LOGIN_PRODUCTION' => 'HiPayPaymentPlugin.config.hashStage',
        'PRIVATE_PASSWORD_PRODUCTION' => 'HiPayPaymentPlugin.config.operationMode',
        'PUBLIC_LOGIN_PRODUCTION' => 'HiPayPaymentPlugin.config.passphraseProduction',
        'PUBLIC_PASSWORD_PRODUCTION' => 'HiPayPaymentPlugin.config.passphraseStage',
        'PASSPHRASE_PRODUCTION' => 'HiPayPaymentPlugin.config.privateLoginProduction',
        'HASH_PRODUCTION' => 'HiPayPaymentPlugin.config.privateLoginStage',
        'PRIVATE_LOGIN_STAGE' => 'HiPayPaymentPlugin.config.privatePasswordProduction',
        'PRIVATE_PASSWORD_STAGE' => 'HiPayPaymentPlugin.config.privatePasswordStage',
        'PUBLIC_LOGIN_STAGE' => 'HiPayPaymentPlugin.config.publicLoginProduction',
        'PUBLIC_PASSWORD_STAGE' => 'HiPayPaymentPlugin.config.publicLoginStage',
        'PASSPHRASE_STAGE' => 'HiPayPaymentPlugin.config.publicPasswordProduction',
        'HASH_STAGE' => 'HiPayPaymentPlugin.config.publicPasswordStage',
    ];

    /**
     * Get the plugin name.
     */
    public static function getModuleName(): string
    {
        $path = explode('\\', __CLASS__);

        return array_pop($path);
    }

    public function install(InstallContext $context): void
    {
        $paymentMethods = [
            CreditCard::class,
        ];

        foreach ($paymentMethods as $paymentMethod) {
            $this->addPaymentMethod($paymentMethod, $context->getContext());
        }

        $this->addDefaultParameters($context->getContext());
    }

    public function uninstall(UninstallContext $context): void
    {
        // Only set the payment method to inactive when uninstalling. Removing the payment method would
        // cause data consistency issues, since the payment method might have been used in several orders
        $this->setPaymentMethodIsActive(false, CreditCard::class, $context->getContext());
    }

    public function activate(ActivateContext $context): void
    {
        $this->setPaymentMethodIsActive(true, CreditCard::class, $context->getContext());
        parent::activate($context);
    }

    public function deactivate(DeactivateContext $context): void
    {
        $this->setPaymentMethodIsActive(false, CreditCard::class, $context->getContext());
        parent::deactivate($context);
    }

    /**
     * Add an HiPay payment method.
     *
     * @throws UnexpectedValueException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    private function addPaymentMethod(string $paymentClassname, Context $context): void
    {
        // Check implementation
        if (!is_subclass_of($paymentClassname, PaymentMethodInterface::class)) {
            throw new UnexpectedValueException('the payment method "'.$paymentClassname.'" must implement interface "'.PaymentMethodInterface::class.'"');
        }

        // Payment method exists already
        if ($this->getPaymentMethodId($paymentClassname)) {
            return;
        }

        if (!isset($this->pluginId)) {
            /** @var PluginIdProvider $pluginIdProvider */
            $pluginIdProvider = $this->container->get(PluginIdProvider::class);
            $this->pluginId = $pluginIdProvider->getPluginIdByBaseClass(static::class, $context);
        }

        $translations = [];
        foreach ($this->getLanguages() as $lang) {
            $translations[] = [
                'languageId' => $lang['id'],
                'name' => $paymentClassname::getName($lang['code']),
                'description' => $paymentClassname::getDescription($lang['code']),
                'customFields' => $paymentClassname::addDefaultCustomFields(),
            ];
        }

        $paymentMethod = [
            'handlerIdentifier' => $paymentClassname,
            'translations' => $translations,
            'pluginId' => $this->pluginId,
        ];

        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->create([$paymentMethod], $context);
    }

    /**
     * Activate or Desactivate a payment Method.
     *
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    private function setPaymentMethodIsActive(bool $active, string $paymentClassname, Context $context): void
    {
        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodId = $this->getPaymentMethodId($paymentClassname);

        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodId) {
            return;
        }

        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];

        $paymentRepository->update([$paymentMethod], $context);
    }

    /**
     * Return the PaymentMethodId if exists.
     *
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    private function getPaymentMethodId(string $paymentClassname): ?string
    {
        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        // Fetch ID for update
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', $paymentClassname));

        return $paymentRepository->searchIds($paymentCriteria, Context::createDefaultContext())->firstId();
    }

    /**
     * Get All installed language with locale code.
     *
     * @return array<string,array<string,mixed>>
     */
    private function getLanguages(): array
    {
        $langMap = [];
        /** @var EntityRepository $languageRepository */
        $languageRepository = $this->container->get('language.repository');

        $criteria = (new Criteria())->addAssociation('locale');
        $languages = $languageRepository->search($criteria, Context::createDefaultContext())->getEntities();

        /** @var LanguageEntity $language */
        foreach ($languages as $language) {
            $langMap[$language->getLocale()->getCode()] = [
                'code' => $language->getLocale()->getCode(),
                'id' => $language->getId(),
            ];
        }

        return $langMap;
    }

    /**
     * Add environment variable as configuration values.
     */
    private function addDefaultParameters(Context $context): void
    {
        $validParams = [];
        foreach (self::PARAMS as $envName => $paramName) {
            if ($value = $_ENV[$envName] ?? null) {
                $validParams[] = [
                    'configurationKey' => $paramName,
                    'configurationValue' => json_encode(['_value' => $value]),
                ];
            }
        }

        /** @var EntityRepository $systemConfigRepository */
        $systemConfigRepository = $this->container->get('system_config.repository');
        $systemConfigRepository->upsert($validParams, $context);
    }
}
