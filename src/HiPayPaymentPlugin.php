<?php

declare(strict_types=1);

namespace HiPay\Payment;

use Composer\InstalledVersions;
use HiPay\Fullservice\Exception\UnexpectedValueException;
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
use HiPay\Payment\PaymentMethod\PaymentMethodInterface;
use HiPay\Payment\PaymentMethod\Paypal;
use HiPay\Payment\PaymentMethod\Przelewy24;
use HiPay\Payment\PaymentMethod\SepaDirectDebit;
use HiPay\Payment\PaymentMethod\Sofort;
use HiPay\Payment\Service\ImageImportService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
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
        'LOG_DEBUG' => 'HiPayPaymentPlugin.config.debugMode',
        // PRODUCTION
        'PRIVATE_LOGIN_PRODUCTION' => 'HiPayPaymentPlugin.config.publicLoginProduction',
        'PRIVATE_PASSWORD_PRODUCTION' => 'HiPayPaymentPlugin.config.privatePasswordProduction',
        'PUBLIC_LOGIN_PRODUCTION' => 'HiPayPaymentPlugin.config.privateLoginProduction',
        'PUBLIC_PASSWORD_PRODUCTION' => 'HiPayPaymentPlugin.config.publicPasswordProduction',
        'PASSPHRASE_PRODUCTION' => 'HiPayPaymentPlugin.config.passphraseProduction',
        'HASH_PRODUCTION' => 'HiPayPaymentPlugin.config.hashProduction',
        // PRODUCTION APPLE PAY
        'PUBLIC_APPLEPAY_LOGIN_PRODUCTION' => 'HiPayPaymentPlugin.config.publicApplePayLoginProduction',
        'PUBLIC_APPLEPAY_PASSWORD_PRODUCTION' => 'HiPayPaymentPlugin.config.publicApplePayPasswordProduction',
        'PRIVATE_APPLEPAY_LOGIN_PRODUCTION' => 'HiPayPaymentPlugin.config.privateApplePayLoginProduction',
        'PRIVATE_APPLEPAY_PASSWORD_PRODUCTION' => 'HiPayPaymentPlugin.config.privateApplePayPasswordProduction',
        'APPLEPAY_PASSPHRASE_PRODUCTION' => 'HiPayPaymentPlugin.config.applePayPassphraseProduction',
        'HASH_PRODUCTION_APPLEPAY' => 'HiPayPaymentPlugin.config.hashProductionApplePay',
        // STAGE
        'PRIVATE_LOGIN_STAGE' => 'HiPayPaymentPlugin.config.privateLoginStage',
        'PRIVATE_PASSWORD_STAGE' => 'HiPayPaymentPlugin.config.privatePasswordStage',
        'PUBLIC_LOGIN_STAGE' => 'HiPayPaymentPlugin.config.publicLoginStage',
        'PUBLIC_PASSWORD_STAGE' => 'HiPayPaymentPlugin.config.publicPasswordStage',
        'PASSPHRASE_STAGE' => 'HiPayPaymentPlugin.config.passphraseStage',
        'HASH_STAGE' => 'HiPayPaymentPlugin.config.hashStage',
        // STAGE APPLE PAY
        'PUBLIC_APPLEPAY_LOGIN_STAGE' => 'HiPayPaymentPlugin.config.publicApplePayLoginStage',
        'PUBLIC_APPLEPAY_PASSWORD_STAGE' => 'HiPayPaymentPlugin.config.publicApplePayPasswordStage',
        'PRIVATE_APPLEPAY_LOGIN_STAGE' => 'HiPayPaymentPlugin.config.privateApplePayLoginStage',
        'PRIVATE_APPLEPAY_PASSWORD_STAGE' => 'HiPayPaymentPlugin.config.privateApplePayPasswordStage',
        'APPLEPAY_PASSPHRASE_STAGE' => 'HiPayPaymentPlugin.config.applePayPassphraseStage',
        'HASH_STAGE_APPLEPAY' => 'HiPayPaymentPlugin.config.hashStageApplePay',
    ];

    private const PAYMENT_METHODS = [
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
    public static function getShopwareVersion(): ?string
    {
        $version = null;
        if (InstalledVersions::isInstalled('shopware/core')) {
            $version = InstalledVersions::getVersion('shopware/core');
        }

        return $version ?? exec("cd ../ && php bin/console --version | awk '{print $2}'");
    }

    public function install(InstallContext $context): void
    {
        foreach (self::PAYMENT_METHODS as $paymentMethod) {
            $this->addPaymentMethod($paymentMethod, $context->getContext());
        }

        $this->addDefaultParameters($context->getContext());
    }

    public function uninstall(UninstallContext $context): void
    {
        // Only set the payment method to inactive when uninstalling. Removing the payment method would
        // cause data consistency issues, since the payment method might have been used in several orders

        foreach (self::PAYMENT_METHODS as $paymentMethod) {
            $this->setPaymentMethodIsActive(
                false,
                $paymentMethod,
                $context->getContext()
            );
        }
    }

    public function activate(ActivateContext $context): void
    {
        foreach (self::PAYMENT_METHODS as $paymentMethod) {
            $this->setPaymentMethodIsActive(
                true,
                $paymentMethod,
                $context->getContext()
            );
        }
        parent::activate($context);
    }

    public function deactivate(DeactivateContext $context): void
    {
        foreach (self::PAYMENT_METHODS as $paymentMethod) {
            $this->setPaymentMethodIsActive(
                false,
                $paymentMethod,
                $context->getContext()
            );
        }
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
        string $classname,
        Context $context
    ): void {
        // Check implementation
        if (!is_subclass_of($classname, PaymentMethodInterface::class)) {
            throw new UnexpectedValueException('The payment method "'.$classname.'" must implement interface "'.PaymentMethodInterface::class.'"');
        }

        // Payment method exists already
        if ($this->getPaymentMethodId($classname)) {
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
                'name' => $classname::getName($lang['code']),
                'description' => $classname::getDescription($lang['code']),
                'customFields' => $classname::addDefaultCustomFields(),
            ];
        }

        $paymentMethod = [
            'handlerIdentifier' => $classname,
            'translations' => $translations,
            'afterOrderEnabled' => true,
            'pluginId' => $this->pluginId,
            'salesChannels' => $this->getSalesChannelIds(),
            'position' => $classname::getPosition(),
            'technicalName' => $classname::getTechnicalName(),
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
        string $classname,
        Context $context,
        string $directory = 'administration/media'
    ): void {
        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get($this->paymentMethodRepoName);

        $paymentMethodId = $this->getPaymentMethodId($classname);

        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodId) {
            return;
        }

        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];

        if ($active) {
            $filename = $classname::getImage();
            if ($filename && $mediaId = $this->addImageToPaymentMethod($filename, $directory, $context)) {
                $paymentMethod['mediaId'] = $mediaId;
            }

            if ($rule = $this->getRule($classname)) {
                $paymentMethod['availabilityRule'] = $rule;
            }
        }

        $paymentRepository->update([$paymentMethod], $context);
    }

    /**
     * Return the PaymentMethodId if exists.
     *
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    private function getPaymentMethodId(string $classname): ?string
    {
        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get($this->paymentMethodRepoName);

        // Fetch ID for update
        $paymentCriteria = (new Criteria())->addFilter(
            new EqualsFilter('handlerIdentifier', $classname)
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
                    'configurationValue' => $paramName === self::PARAMS['LOG_DEBUG'] ? boolval($value) : $value,
                ];
            }
        }

        /** @var EntityRepository $systemConfigRepository */
        $systemConfigRepository = $this->container->get(
            'system_config.repository'
        );

        // Delete default fields when set by env vars
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

    /**
     * Add the rule for the payement method.
     *
     * @return array<string,mixed>|null
     */
    private function getRule(string $classname): ?array
    {
        $context = Context::createDefaultContext();

        /** @var EntityRepository */
        $ruleRepo = $this->container->get('rule.repository');
        $ruleCriteria = (new Criteria())->addFilter(new ContainsFilter('customFields', $classname::getProductCode()));

        // Existing rule
        if ($ruleId = $ruleRepo->searchIds($ruleCriteria, $context)->firstId()) {
            return ['id' => $ruleId];
        }

        $countries = $classname::getCountries();
        $currencies = $classname::getCurrencies();
        $minAmount = $classname::getMinAmount();
        $maxAmount = $classname::getMaxAmount();

        // No rule
        if (!$countries && !$currencies && !$minAmount && !$maxAmount) {
            return null;
        }

        $conditions = [[
            'id' => $andId = Uuid::randomHex(),
            'type' => 'andContainer',
            'position' => 0,
        ]];

        if ($countries) {
            $filter = new OrFilter();
            foreach ($countries as $country) {
                $filter->addQuery(new EqualsFilter('iso', $country));
            }

            /** @var EntityRepository */
            $countryRepo = $this->container->get('country.repository');
            $countryIds = $countryRepo->searchIds((new Criteria())->addFilter($filter), $context)->getIds();

            $conditions[] = [
                'type' => 'customerBillingCountry',
                'position' => 1,
                'value' => ['operator' => Rule::OPERATOR_EQ, 'countryIds' => $countryIds],
                'parentId' => $andId,
            ];
        }

        if ($currencies) {
            $filter = new OrFilter();
            foreach ($currencies as $currency) {
                $filter->addQuery(new EqualsFilter('isoCode', $currency));
            }

            /** @var EntityRepository */
            $currencyRepo = $this->container->get('currency.repository');
            $currencyIds = $currencyRepo->searchIds((new Criteria())->addFilter($filter), $context)->getIds();

            $conditions[] = [
                'type' => 'currency',
                'position' => 0,
                'value' => ['operator' => Rule::OPERATOR_EQ, 'currencyIds' => $currencyIds],
                'parentId' => $andId,
            ];
        }

        if ($minAmount) {
            $conditions[] = [
                'type' => 'cartCartAmount',
                'position' => 1,
                'value' => ['operator' => Rule::OPERATOR_GTE, 'amount' => $minAmount],
                'parentId' => $andId,
            ];
        }

        if ($maxAmount) {
            $conditions[] = [
                'type' => 'cartCartAmount',
                'position' => 1,
                'value' => ['operator' => Rule::OPERATOR_LTE, 'amount' => $maxAmount],
                'parentId' => $andId,
            ];
        }

        return [
            'name' => $classname::getName('en-GB').' rule',
            'priority' => 1,
            'customFields' => ['hipay-payment' => $classname::getProductCode()],
            'conditions' => $conditions,
        ];
    }
}

if (file_exists(dirname(__DIR__).'/vendor/autoload.php')) {
    require_once dirname(__DIR__).'/vendor/autoload.php';
}
