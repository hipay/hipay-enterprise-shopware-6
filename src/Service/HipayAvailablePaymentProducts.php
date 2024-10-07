<?php

namespace HiPay\Payment\Service;

use Exception;

class HipayAvailablePaymentProducts
{
    /** @var self|null */
    private static $instance = null;

    /** @var mixed */
    private $hipayConfigTool;

    /** @var string */
    private $apiUsername;

    /** @var string */
    private $apiPassword;

    /** @var string */
    private $authorizationHeader;

    /** @var string */
    private $baseUrl;

    /**
     * @param mixed $hipayConfigTool
     */
    public function __construct($hipayConfigTool)
    {
        $this->hipayConfigTool = $hipayConfigTool;
        $this->setCredentialsAndUrl();
        $this->generateAuthorizationHeader();
    }

    /**
     * @param mixed $hipayConfigTool
     * @return self
     */
    public static function getInstance($hipayConfigTool)
    {
        if (self::$instance === null) {
            self::$instance = new self($hipayConfigTool);
        }

        return self::$instance;
    }

    private function setCredentialsAndUrl(): void
    {
        $this->apiUsername = $this->hipayConfigTool->getPublicLogin();
        $this->apiPassword = $this->hipayConfigTool->getPublicPassword();
        $this->baseUrl = $this->hipayConfigTool->isTestActivated()
            ? 'https://stage-secure-gateway.hipay-tpp.com/rest/v2/'
            : 'https://secure-gateway.hipay-tpp.com/rest/v2/';
    }

    private function generateAuthorizationHeader(): void
    {
        $credentials = $this->apiUsername . ':' . $this->apiPassword;
        $encodedCredentials = base64_encode($credentials);
        $this->authorizationHeader = 'Basic ' . $encodedCredentials;
    }

    /**
     * @param string $paymentProduct
     * @param string $eci
     * @param string $operation
     * @param string $withOptions
     * @return array
     * @throws Exception
     */
    public function getAvailablePaymentProducts(
        $paymentProduct = 'paypal',
        $eci = '7',
        $operation = '4',
        $withOptions = 'true'
    ) {
        $url = $this->baseUrl . 'available-payment-products.json';
        $url .= '?eci=' . urlencode($eci);
        $url .= '&operation=' . urlencode($operation);
        $url .= '&payment_product=' . urlencode($paymentProduct);
        $url .= '&with_options=' . urlencode($withOptions);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $this->authorizationHeader,
            'Accept: application/json',
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, true);
    }
}
