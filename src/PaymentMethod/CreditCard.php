<?php

namespace HiPay\Payment\PaymentMethod;

use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\CardTokenPaymentMethod;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Credit card payment Methods.
 */
class CreditCard extends AbstractPaymentMethod
{
    public static bool $haveHostedFields = true;

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
    public static function getImage(): ?string
    {
        return 'credit_card.svg';
    }

    /** {@inheritDoc} */
    public static function getRule(ContainerInterface $container): ?array
    {
        return null;
    }

    /** {@inheritDoc} */
    public static function addDefaultCustomFields(): array
    {
        return parent::addDefaultCustomFields() + [
            'cards' => ['cb', 'visa', 'mastercard', 'american-express', 'bcmc', 'maestro'],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @throws BadRequestException
     */
    protected function hydrateHostedFields(OrderRequest $orderRequest, array $payload): OrderRequest
    {
        $paymentMethod = new CardTokenPaymentMethod();
        $paymentMethod->cardtoken = $payload['token'];
        $paymentMethod->eci = 7;
        $paymentMethod->authentication_indicator = $this->config->get3DSAuthenticator();

        // @phpstan-ignore-next-line
        $orderRequest->paymentMethod = $paymentMethod;

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
