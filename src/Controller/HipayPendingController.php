<?php

namespace HiPay\Payment\Controller;

use HiPay\Fullservice\Enum\Transaction\TransactionState;
use HiPay\Fullservice\Enum\Transaction\TransactionStatus;
use HiPay\Fullservice\Gateway\Model\Transaction;
use HiPay\Payment\Service\HiPayHttpClientService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class HipayPendingController extends StorefrontController
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    /**
     * @param EntityRepository<\Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection> $orderTransactionRepository
     */
    public function __construct(
        private HiPayHttpClientService $clientService,
        private EntityRepository $orderTransactionRepository,
        private LoggerInterface $logger
    ) {}

    #[Route(
        path: '/hipay/pending',
        name: 'frontend.hipay.pending',
        options: ['seo' => false],
        methods: ['GET'],
        defaults: ['_noStore' => true]
    )]
    public function pending(Request $request, SalesChannelContext $context): Response
    {
        $token = $request->query->getString('_sw_payment_token');
        $transactionId = $request->query->getAlnum('transactionId');

        if ($token === '' || $transactionId === '') {
            throw new NotFoundHttpException();
        }

        return $this->renderStorefront('@HiPayPayments/storefront/page/hipay/pending.html.twig', [
            'finalizeUrl' => $this->generateUrl(
                'payment.finalize.transaction',
                ['_sw_payment_token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'statusUrl' => $this->generateUrl(
                'frontend.hipay.pending.status',
                ['transactionId' => $transactionId]
            ),
        ]);
    }

    #[Route(
        path: '/hipay/pending/status/{transactionId}',
        name: 'frontend.hipay.pending.status',
        options: ['seo' => false],
        methods: ['GET'],
        defaults: ['XmlHttpRequest' => true, '_noStore' => true]
    )]
    public function status(string $transactionId, SalesChannelContext $context): JsonResponse
    {
        $criteria = (new Criteria([$transactionId]))->addAssociation('order');
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context->getContext())->first();

        if (!$orderTransaction instanceof OrderTransactionEntity || $orderTransaction->getOrder() === null) {
            return new JsonResponse(['status' => self::STATUS_FAILED], Response::HTTP_NOT_FOUND);
        }

        $hipayOrderId = $orderTransaction->getOrder()->getOrderNumber()
            . '-' . dechex(crc32($transactionId));

        try {
            $transactions = $this->clientService
                ->getConfiguredClient()
                ->requestOrderTransactionInformation($hipayOrderId);
        } catch (\Throwable $e) {
            $this->logger->warning('Unable to poll HiPay transaction status: ' . $e->getMessage());

            return new JsonResponse(['status' => self::STATUS_PENDING]);
        }

        return new JsonResponse(['status' => self::resolveStatusFromTransactions($transactions)]);
    }

    public static function resolveStatusFromTransactions(array $transactions): string
    {
        $failed = false;

        foreach ($transactions as $transaction) {
            if (!$transaction instanceof Transaction) {
                continue;
            }

            $status = self::resolveStatus($transaction);

            if ($status === self::STATUS_SUCCESS) {
                return self::STATUS_SUCCESS;
            }

            $failed = $failed || $status === self::STATUS_FAILED;
        }

        return $failed ? self::STATUS_FAILED : self::STATUS_PENDING;
    }

    public static function resolveStatus(?Transaction $transaction): string
    {
        if ($transaction === null) {
            return self::STATUS_PENDING;
        }

        if ($transaction->getStatus() === TransactionStatus::CANCELLED) {
            return self::STATUS_FAILED;
        }

        return match ($transaction->getState()) {
            TransactionState::COMPLETED => self::STATUS_SUCCESS,
            TransactionState::DECLINED, TransactionState::ERROR => self::STATUS_FAILED,
            default => self::STATUS_PENDING,
        };
    }
}
