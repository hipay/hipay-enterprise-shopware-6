<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Gateway\Model\Request\ThreeDSTwo\BrowserInfo;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\CardTokenPaymentMethod;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Credit card payment Methods.
 */
class CreditCard extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Credit Cards',
            'de-DE' => 'Kreditkarten',
        ];

        return $names[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'Use your credit cards to safely pay through our PCI DSS certified payment provider',
            'de-DE' => 'Verwenden Sie Ihre Kreditkarten, um sicher über unseren PCI DSS-zertifizierten Zahlungsanbieter zu bezahlen',
        ];

        return $descriptions[$lang] ?? null;
    }

    /** {@inheritDoc} */
    public static function addDefaultCustomFields(): array
    {
        return [
            'cards' => ['cb', 'visa', 'mastercard', 'american-express', 'bancontact', 'maestro'],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @throws BadRequestException
     */
    protected function hydrateHostedFields(OrderRequest $orderRequest): OrderRequest
    {
        $payload = json_decode($this->request->get('hipay-response'), true);

        $paymentMethod = new CardTokenPaymentMethod();
        $paymentMethod->cardtoken = $payload['token'];
        $paymentMethod->eci = 7;
        $paymentMethod->authentication_indicator = $this->config->get3DSAuthenticator();

        $browserInfo = new BrowserInfo();
        $browserInfo->ipaddr = $this->request->getClientIp();
        $browserInfo->http_accept = 'application/json';
        $browserInfo->http_user_agent = $payload['browser_info']['http_user_agent'];
        $browserInfo->java_enabled = $payload['browser_info']['java_enabled'];
        $browserInfo->javascript_enabled = $payload['browser_info']['javascript_enabled'];
        $browserInfo->language = $payload['browser_info']['language'];
        $browserInfo->color_depth = $payload['browser_info']['color_depth'];
        $browserInfo->screen_height = $payload['browser_info']['screen_height'];
        $browserInfo->screen_width = $payload['browser_info']['screen_width'];
        $browserInfo->timezone = $payload['browser_info']['timezone'];

        // @phpstan-ignore-next-line
        $orderRequest->paymentMethod = $paymentMethod;
        $orderRequest->browser_info = $browserInfo;

        $orderRequest->payment_product = $payload['payment_product'];
        $orderRequest->device_fingerprint = $payload['device_fingerprint'];

        return $orderRequest;
    }

    /**
     * {@inheritDoc}
     *
     * @throws BadRequestException
     */
    protected function hydrateHostedPage(HostedPaymentPageRequest $orderRequest, AsyncPaymentTransactionStruct $transaction): HostedPaymentPageRequest
    {
        $customFields = $transaction->getOrderTransaction()->getPaymentMethod()->getCustomFields();

        $orderRequest->payment_product_list = implode(',', $customFields['cards'] ?? []);

        return $orderRequest;
    }
}