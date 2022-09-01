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
        $this->configHipay = $systemConfigService->get(HiPayPaymentPlugin::getModuleName() . '.config');
    }

    /**
     * Get the environment name
     *  
     * @return string 
     */
    public function getEnvironment(): string
    {
        return $this->configHipay->get('environment');
    }

    /**
     * Check if the environment is on prod
     *  
     * @return string 
     */
    public function isProdActivated(): bool
    {
        return strtolower($this->getEnvironment()) === Configuration::API_ENV_PRODUCTION;
    }

    /**
     * Check if the environment is on test
     *  
     * @return string 
     */
    public function isTestActivated(): bool
    {
        return !$this->isProdActivated();
    }

    /**
     * Get the private login based on the environment
     *  
     * @return string 
     */
    public function getPrivateLogin(): string
    {
        return $this->configHipay->get($this->isProdActivated() ? 'privateLoginProduction' : 'privateLoginStage');
    }

    /**
     * Get the private password based on the environment
     *  
     * @return string 
     */
    public function getPrivatePassword(): string
    {
        return $this->configHipay->get($this->isProdActivated() ? 'privatePasswordProduction' : 'privatePasswordStage');
    }

    /**
     * Get the public login based on the environment
     *  
     * @return string 
     */
    public function getPublicLogin(): string
    {
        return $this->configHipay->get($this->isProdActivated() ? 'publicLoginProduction' : 'publicLoginStage');
    }

    /**
     * Get the public password based on the environment
     *  
     * @return string 
     */
    public function getPublicPassword(): string
    {
        return $this->configHipay->get($this->isProdActivated() ? 'publicPasswordProduction' : 'publicPasswordStage');
    }

    /**
     * Get the hash based on the environment
     *  
     * @return string 
     */
    public function getHash(): string
    {
        
        return $this->configHipay->get($this->isProdActivated() ? 'hashProduction' : 'hashStage');
    }

    /**
     * Get the passphrase based on the environment
     *  
     * @return string 
     */
    public function getPassphrase(): string
    {
        return $this->configHipay->get($this->isProdActivated() ? 'passphraseProduction' : 'passphraseStage');
    }

    /**
     * Get the capture mode name
     * 
     * @return string 
     */
    public function getCaptureMode(): string {
        return $this->configHipay->get('captureMode');
    }

    /**
     * check if the capture mode is manual
     * 
     * @return bool 
     */
    public function isCaptureManuel(): bool {
        return $this->getCaptureMode() === 'manuel';
    }

     /**
     * check if the capture mode is auto
     * 
     * @return bool 
     */
    public function isCaptureAuto(): bool {
        return !$this->isCaptureManuel();
    }

    /**
     * Get the operation mode name
     * 
     * @return string 
     */
    public function getOperationMode(): string {
        return $this->configHipay->get('operationMode');
    }

    /**
     * Check if the operation mode is hosted fields
     * 
     * @return bool 
     */
    public function isHostedFields(): bool {
        return $this->getOperationMode() === 'hostedFields';
    }

     /**
     * Check if the operation mode is hosted page
     * 
     * @return bool 
     */
    public function isHostedPage(): bool {
        return !$this->isHostedFields();
    }


    /**
     * Check if the one click paiement is activated
     * 
     * @return bool 
     */
    public function isOneClickPayment(): bool {
        return ($this->configHipay->get('oneClickPayment'));
    }

    /**
     * Check if cart remembering is activated
     * 
     * @return bool 
     */
    public function isRememberCart(): bool {
        return ($this->configHipay->get('rememberCart'));
    }

    /**
     * Check if debug mode is activated
     * 
     * @return bool 
     */
    public function isDebugMode(): bool {
        return ($this->configHipay->get('debugMode'));
    }

    /**
     * get the 3-D Secure authenticator flag
     */
    public function get3DSAuthenticator(): int {
        return $this->configHipay->get('authFlag3DS') ?? 0;
    }


    /**
     * Check if debug mode is activated
     * 
     * @return bool 
     */
    public function getCustomStyle() : array {

        $styles = [];       

        if($this->isHostedFields()) {

            $fields = [
                'hostedFieldsTextColor',
                'hostedFieldsFontFamilly',
                'hostedFieldsFontSize',
                'hostedFieldsFontWeight',
                'hostedFieldsPlaceholderColor',
                'hostedFieldsCaretColor',
                'hostedFieldsIconColor'
            ];

            foreach($fields as $field) {
                if($this->configHipay->get($field)) {
                    $styles[$field] = $this->configHipay->get($field);
                }
            }
        }

        return $styles;
    }



}
