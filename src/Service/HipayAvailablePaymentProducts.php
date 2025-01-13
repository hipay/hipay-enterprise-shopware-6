<?php

namespace HiPay\Payment\Service;

use HiPay\Payment\Exception\HipayApiException;

class HipayAvailablePaymentProducts
{
    private static $instance;

    private $apiUsername;

    private $apiPassword;

    private $authorizationHeader;

    private $baseUrl;

    public function __construct(
        private $hipayConfigTool
    ) {
        $this->setCredentialsAndUrl();
        $this->generateAuthorizationHeader();
    }

    /**
     * @return self
     */
    public static function getInstance($hipayConfigTool)
    {
        if ( self::$instance === null ) {
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
     *
     * @return array
     *
     * @throws \Exception
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
            throw new HipayApiException('Curl error: ' . curl_error($ch), curl_errno($ch));
        }

        curl_close($ch);

        return json_decode($response, true);
    }
}
