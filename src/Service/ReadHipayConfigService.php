<?php

namespace HiPay\Payment\Service;

use HiPay\Fullservice\HTTP\Configuration\Configuration;
use HiPay\Payment\HiPayPaymentPlugin;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ReadHipayConfigService
{
    private SystemConfigService $configHipay;

    public function __construct(SystemConfigService $systemConfigService)
    {
        // @phpstan-ignore-next-line
        $this->configHipay = $systemConfigService->get(HiPayPaymentPlugin::getModuleName().'.config');
    }

    /**
     * Get the environment name.
     */
    public function getEnvironment(): string
    {
        return (string) $this->configHipay->getString('environment');
    }

    /**
     * Check if the environment is on prod.
     */
    public function isProdActivated(): bool
    {
        return Configuration::API_ENV_PRODUCTION === strtolower($this->getEnvironment());
    }

    /**
     * Check if the environment is on test.
     */
    public function isTestActivated(): bool
    {
        return !$this->isProdActivated();
    }

    /**
     * Get the private login based on the environment.
     */
    public function getPrivateLogin(): string
    {
        return $this->configHipay->getString($this->isProdActivated() ? 'privateLoginProduction' : 'privateLoginStage');
    }

    /**
     * Get the private password based on the environment.
     */
    public function getPrivatePassword(): string
    {
        return $this->configHipay->getString($this->isProdActivated() ? 'privatePasswordProduction' : 'privatePasswordStage');
    }

    /**
     * Get the public login based on the environment.
     */
    public function getPublicLogin(): string
    {
        return $this->configHipay->getString($this->isProdActivated() ? 'publicLoginProduction' : 'publicLoginStage');
    }

    /**
     * Get the public password based on the environment.
     */
    public function getPublicPassword(): string
    {
        return $this->configHipay->getString($this->isProdActivated() ? 'publicPasswordProduction' : 'publicPasswordStage');
    }

    /**
     * Get the hash based on the environment.
     */
    public function getHash(): string
    {
        return $this->configHipay->getString($this->isProdActivated() ? 'hashProduction' : 'hashStage');
    }

    /**
     * Get the passphrase based on the environment.
     */
    public function getPassphrase(): string
    {
        return $this->configHipay->getString($this->isProdActivated() ? 'passphraseProduction' : 'passphraseStage');
    }

    /**
     * Get the capture mode name.
     */
    public function getCaptureMode(): string
    {
        return $this->configHipay->getString('captureMode');
    }

    /**
     * check if the capture mode is manual.
     */
    public function isCaptureManual(): bool
    {
        return 'manual' === $this->getCaptureMode();
    }

    /**
     * check if the capture mode is auto.
     */
    public function isCaptureAuto(): bool
    {
        return !$this->isCaptureManual();
    }

    /**
     * Get the operation mode name.
     */
    public function getOperationMode(): string
    {
        return $this->configHipay->getString('operationMode');
    }

    /**
     * Check if the operation mode is hosted fields.
     */
    public function isHostedFields(): bool
    {
        return 'hostedFields' === $this->getOperationMode();
    }

    /**
     * Check if the operation mode is hosted page.
     */
    public function isHostedPage(): bool
    {
        return !$this->isHostedFields();
    }

    /**
     * Check if the one click paiement is activated.
     */
    public function isOneClickPayment(): bool
    {
        return $this->configHipay->getBool('oneClickPayment');
    }

    /**
     * Check if cart remembering is activated.
     */
    public function isRememberCart(): bool
    {
        return $this->configHipay->getBool('rememberCart');
    }

    /**
     * Check if debug mode is activated.
     */
    public function isDebugMode(): bool
    {
        return $this->configHipay->getBool('debugMode');
    }

    /**
     * get the 3-D Secure authenticator flag.
     */
    public function get3DSAuthenticator(): int
    {
        try {
            return $this->configHipay->getInt('authFlag3DS');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Check if debug mode is activated.
     *
     * @return array<string,mixed>
     */
    public function getCustomStyle(): array
    {
        $styles = [];

        if ($this->isHostedFields()) {
            $fields = [
                'hostedFieldsTextColor',
                'hostedFieldsFontFamilly',
                'hostedFieldsFontSize',
                'hostedFieldsFontWeight',
                'hostedFieldsPlaceholderColor',
                'hostedFieldsCaretColor',
                'hostedFieldsIconColor',
            ];

            foreach ($fields as $field) {
                if ($this->configHipay->get($field)) {
                    $styles[$field] = $this->configHipay->get($field);
                }
            }
        }

        return $styles;
    }
}
