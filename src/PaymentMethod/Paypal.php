<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Payment\Logger\HipayLogger;
use HiPay\Payment\Service\HiPayHttpClientService;
use HiPay\Payment\Service\ReadHipayConfigService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Paypal payment Methods.
 */
class Paypal extends AbstractPaymentMethod
{
    /** {@inheritDoc} */
    protected const PAYMENT_CODE = 'paypal';

    /** {@inheritDoc} */
    protected const PAYMENT_POSITION = 20;

    /** {@inheritDoc} */
    protected const PAYMENT_IMAGE = 'paypal.svg';

    protected static PaymentProduct $paymentConfig;

    protected EntityRepository $transactionRepo;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ReadHipayConfigService $config,
        HiPayHttpClientService $clientService,
        RequestStack $requestStack,
        LocaleProvider $localeProvider,
        EntityRepository $orderCustomerRepository,
        HipayLogger $hipayLogger,
        EntityRepository $orderTransactionRepository
    ) {
        parent::__construct(
            $transactionStateHandler,
            $config,
            $clientService,
            $requestStack,
            $localeProvider,
            $orderCustomerRepository,
            $hipayLogger,
        );

        $this->transactionRepo = $orderTransactionRepository;
    }

    public static function getName(string $lang): ?string
    {
        $names = [
            'en-GB' => 'Paypal',
            'de-DE' => 'Paypal',
        ];

        return $names[$lang] ?? null;
    }

    public static function getDescription(string $lang): ?string
    {
        $descriptions = [
            'en-GB' => 'PayPal is an American company offering an online payment service system worldwide',
            'de-DE' => 'PayPal ist ein amerikanisches Unternehmen, das weltweit ein Online-Zahlungsdienstsystem anbietet',
        ];

        return $descriptions[$lang] ?? null;
    }

    public static function addDefaultCustomFields(): array
    {
        return [
            'color' => 'gold',
            'shape' => 'pill',
            'label' => 'paypal',
            'height' => '40',
            'bnpl' => true,
        ];
    }

    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload, AsyncPaymentTransactionStruct $transaction): OrderRequest
    {
        if ('paypal' === $orderRequest->payment_product && isset($payload['orderID'])) {
            $providerData = ['paypal_id' => $payload['orderID']];
            $orderRequest->provider_data = json_encode($providerData);
        }

        return $orderRequest;
    }

    protected function hydrateHostedPage(
        HostedPaymentPageRequest $orderRequest,
        AsyncPaymentTransactionStruct $transaction
    ): HostedPaymentPageRequest {
        $customFields = $transaction->getOrderTransaction()->getPaymentMethod()->getCustomFields();

        $orderRequest->paypal_v2_label = $customFields['label'] ?? null;
        $orderRequest->paypal_v2_shape = $customFields['shape'] ?? null;
        $orderRequest->paypal_v2_color = $customFields['color'] ?? null;
        $orderRequest->paypal_v2_height = $customFields['height'] ?? null;
        $orderRequest->paypal_v2_bnpl = $customFields['bnpl'] ?? null;

        return $orderRequest;
    }
}
