<?php

declare(strict_types=1);

namespace HiPay\Payment;

use Composer\InstalledVersions;
use HiPay\Fullservice\Exception\UnexpectedValueException;
use HiPay\Payment\PaymentMethod\CreditCard;
use HiPay\Payment\PaymentMethod\PaymentMethodInterface;
use HiPay\Payment\Service\ImageImportService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
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
        'OPERATION_MODE' => 'HiPayPaymentPlugin.config.operationMode',
        'PRIVATE_LOGIN_PRODUCTION' => 'HiPayPaymentPlugin.config.publicLoginProduction',
        'PRIVATE_PASSWORD_PRODUCTION' => 'HiPayPaymentPlugin.config.privatePasswordProduction',
        'PUBLIC_LOGIN_PRODUCTION' => 'HiPayPaymentPlugin.config.privateLoginProduction',
        'PUBLIC_PASSWORD_PRODUCTION' => 'HiPayPaymentPlugin.config.publicPasswordProduction',
        'PASSPHRASE_PRODUCTION' => 'HiPayPaymentPlugin.config.passphraseProduction',
        'HASH_PRODUCTION' => 'HiPayPaymentPlugin.config.hashProduction',
        'PRIVATE_LOGIN_STAGE' => 'HiPayPaymentPlugin.config.privateLoginStage',
        'PRIVATE_PASSWORD_STAGE' => 'HiPayPaymentPlugin.config.privatePasswordStage',
        'PUBLIC_LOGIN_STAGE' => 'HiPayPaymentPlugin.config.publicLoginStage',
        'PUBLIC_PASSWORD_STAGE' => 'HiPayPaymentPlugin.config.publicPasswordStage',
        'PASSPHRASE_STAGE' => 'HiPayPaymentPlugin.config.passphraseStage',
        'HASH_STAGE' => 'HiPayPaymentPlugin.config.hashStage',
    ];

    private string $paymentMethodRepoName = 'payment_method.repository';

    /**
     * Get the plugin name.
     */
    public static function getModuleName(): string
    {
        $path = explode('\\', __CLASS__);

        return array_pop($path);
    }

    /**
     * Get the plugin version.
     */
    public static function getModuleVersion(): string
    {
        $content = file_get_contents(__DIR__.'/../composer.json');
        if (!$content) {
            return '';
        }

        $composer = json_decode($content);

        return $composer->version;
    }

    /**
     * Get Shopware version.
     */
    public static function getShopwareVersion(): string
    {
        return InstalledVersions::isInstalled('shopware/platform')
            ? InstalledVersions::getVersion('shopware/platform')
            : InstalledVersions::getVersion('shopware/core');
    }

    public function install(InstallContext $context): void
    {
        $paymentMethods = [CreditCard::class];

        foreach ($paymentMethods as $paymentMethod) {
            $this->addPaymentMethod($paymentMethod, $context->getContext());
        }

        $this->addDefaultParameters($context->getContext());
    }

    public function uninstall(UninstallContext $context): void
    {
        // Only set the payment method to inactive when uninstalling. Removing the payment method would
        // cause data consistency issues, since the payment method might have been used in several orders
        $this->setPaymentMethodIsActive(
            false,
            CreditCard::class,
            $context->getContext()
        );
    }

    public function activate(ActivateContext $context): void
    {
        $this->setPaymentMethodIsActive(
            true,
            CreditCard::class,
            $context->getContext(),
            'credit_card.svg',
            'administration/media'
        );
        parent::activate($context);
    }

    public function deactivate(DeactivateContext $context): void
    {
        $this->setPaymentMethodIsActive(
            false,
            CreditCard::class,
            $context->getContext()
        );
        parent::deactivate($context);
    }

    /**
     * Add an HiPay payment method.
     *
     * @throws UnexpectedValueException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    private function addPaymentMethod(
        string $paymentClassname,
        Context $context
    ): void {
        // Check implementation
        if (!is_subclass_of($paymentClassname, PaymentMethodInterface::class)) {
            throw new UnexpectedValueException('The payment method "'.$paymentClassname.'" must implement interface "'.PaymentMethodInterface::class.'"');
        }

        // Payment method exists already
        if ($this->getPaymentMethodId($paymentClassname)) {
            return;
        }

        if (!isset($this->pluginId)) {
            /** @var PluginIdProvider $pluginIdProvider */
            $pluginIdProvider = $this->container->get(PluginIdProvider::class);
            $this->pluginId = $pluginIdProvider->getPluginIdByBaseClass(
                static::class,
                $context
            );
        }

        $translations = [];
        foreach ($this->getLanguages() as $lang) {
            $translations[] = [
                'languageId' => $lang['id'],
                'name' => $paymentClassname::getName($lang['code']),
                'description' => $paymentClassname::getDescription(
                    $lang['code']
                ),
                'customFields' => $paymentClassname::addDefaultCustomFields(),
            ];
        }

        $paymentMethod = [
            'handlerIdentifier' => $paymentClassname,
            'translations' => $translations,
            'afterOrderEnabled' => true,
            'pluginId' => $this->pluginId,
            'salesChannels' => $this->getSalesChannelIds(),
        ];

        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get($this->paymentMethodRepoName);
        $paymentRepository->create([$paymentMethod], $context);
    }

    /**
     * Activate or Desactivate a payment Method.
     *
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    private function setPaymentMethodIsActive(
        bool $active,
        string $paymentClassname,
        Context $context,
        ?string $filename = null,
        string $directory = ''
    ): void {
        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get($this->paymentMethodRepoName);

        $paymentMethodId = $this->getPaymentMethodId($paymentClassname);

        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodId) {
            return;
        }

        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];

        if ($filename && $mediaId = $this->addImageToPaymentMethod($filename, $directory, $context)) {
            $paymentMethod['mediaId'] = $mediaId;
        }

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
        $paymentRepository = $this->container->get($this->paymentMethodRepoName);

        // Fetch ID for update
        $paymentCriteria = (new Criteria())->addFilter(
            new EqualsFilter('handlerIdentifier', $paymentClassname)
        );

        return $paymentRepository
            ->searchIds($paymentCriteria, Context::createDefaultContext())
            ->firstId();
    }

    /**
     * Get all sales channel id.
     *
     * @return array<string,array<string,string>>
     */
    private function getSalesChannelIds(): array
    {
        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get('sales_channel.repository');

        return $paymentRepository
            ->searchIds(new Criteria(), Context::createDefaultContext())
            ->getData();
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
        $languages = $languageRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();

        /** @var LanguageEntity $language */
        foreach ($languages as $language) {
            $langMap[$language->getLocale()->getCode()] = [
                'code' => $language->getLocale()->getCode(),
                'id' => $language->getId(),
            ];
        }

        return $langMap;
    }

    private function addImageToPaymentMethod(string $filename, string $directory, Context $context): ?string
    {
        /** @var ImageImportService $imageImportService */
        $imageImportService = $this->container->get(ImageImportService::class);

        // Upload credit card image to media library
        return $imageImportService->addImageToMediaFromFile($filename, $directory, 'payment_method', $context);
    }

    /**
     * Add environment variable as configuration values.
     */
    private function addDefaultParameters(Context $context): void
    {
        $validParams = [];
        $deleteKeys = [];
        foreach (self::PARAMS as $envName => $paramName) {
            if ($value = $_ENV[$envName] ?? null) {
                $deleteKeys[] = $paramName;
                $validParams[] = [
                    'configurationKey' => $paramName,
                    'configurationValue' => $value,
                ];
            }
        }

        /** @var EntityRepository $systemConfigRepository */
        $systemConfigRepository = $this->container->get(
            'system_config.repository'
        );

        // Delete default fields when set bey env vars
        $critera = new Criteria();
        $critera->addFilter(
            new EqualsAnyFilter('configurationKey', $deleteKeys)
        );
        $ids = $systemConfigRepository->searchIds($critera, $context);

        if ($ids->getTotal()) {
            $systemConfigRepository->delete(
                array_values($ids->getData()),
                $context
            );
        }

        $systemConfigRepository->create($validParams, $context);
    }
}

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

