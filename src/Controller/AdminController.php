<?php

namespace HiPay\Payment\Controller;

use HiPay\Fullservice\Enum\Transaction\Operation;
use HiPay\Fullservice\HTTP\Configuration\Configuration;
use HiPay\Payment\Core\Checkout\Payment\Capture\OrderCaptureCollection;
use HiPay\Payment\Core\Checkout\Payment\Capture\OrderCaptureEntity;
use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderCollection;
use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderEntity;
use HiPay\Payment\Core\Checkout\Payment\Refund\OrderRefundCollection;
use HiPay\Payment\Core\Checkout\Payment\Refund\OrderRefundEntity;
use HiPay\Payment\Enum\HipayLoggerChannel;
use HiPay\Payment\Formatter\Request\MaintenanceRequestFormatter;
use HiPay\Payment\HiPayPaymentPlugin;
use HiPay\Payment\Service\HiPayHttpClientService;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[WithMonologChannel(HipayLoggerChannel::API)]
#[Route(defaults: ['_routeScope' => ['administration']])]
class AdminController extends AbstractController
{

    /**
     * @param EntityRepository<HipayOrderCollection> $hipayOrderRepository
     * @param EntityRepository<OrderCaptureCollection> $hipayOrderCaptureRepository
     * @param EntityRepository<OrderRefundCollection> $hipayOrderRefundRepository
     */
    public function __construct(
        private EntityRepository  $hipayOrderRepository,
        private EntityRepository  $hipayOrderCaptureRepository,
        private EntityRepository  $hipayOrderRefundRepository,
        protected LoggerInterface $logger
    )
    {
    }


    #[Route(path: "/api/_action/hipay/checkAccess")]
    public function checkAccess(RequestDataBag $params, HiPayHttpClientService $clientService): JsonResponse
    {
        foreach (['public', 'private'] as $scope) {
            try {
                $conf = $this->extractConfigurationFromPluginConfig(
                    $params,
                    $scope
                );

                $clientService->getClient($conf)->requestSecuritySettings();
            } catch (\Exception $e) {
                $message = "Error on $scope key : " . $e->getMessage();

                /* @infection-ignore-all */
                $this->logger->error($message);
                return new JsonResponse(['success' => false, 'message' => $message], Response::HTTP_BAD_REQUEST);
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Access granted',
        ]);
    }


    #[Route(path: "/api/_action/hipay/capture")]
    public function capture(RequestDataBag $params, HiPayHttpClientService $clientService, SalesChannelContext $salesChannelContext): JsonResponse
    {
        if (!is_string($params->get('hipayOrder'))) {
            return new JsonResponse(['success' => false, 'message' => 'HiPay Order parameter is mandatory'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $hipayOrderData = json_decode($params->get('hipayOrder'));
            $captureAmount = floatval($params->get('amount'));

            $context = $salesChannelContext->getContext();

            // Search HiPay order entity by ID
            $hipayOrderCriteria = (new Criteria([$hipayOrderData->id]))
                ->addAssociations(['captures', 'transaction.paymentMethod'])
                ->setLimit(1);

            /** @var HipayOrderEntity */
            $hipayOrder = $this->hipayOrderRepository->search($hipayOrderCriteria, $context)->getEntities()->first();

            $config = $hipayOrder->getTransaction()->getPaymentMethod()->getExtension('hipayConfig');
            $totalTransaction = $hipayOrder->getTransaction()->getAmount()->getTotalPrice();

            if (!boolval($config['allowPartialCapture']) && $captureAmount !== $totalTransaction) {
                return new JsonResponse(['success' => false, 'message' => 'Only the full capture is allowed'], Response::HTTP_BAD_REQUEST);
            }

            $isApplePay = 'apple_pay' === $hipayOrder->getTransaction()->getPaymentMethod()->getShortName();

            $maintenanceRequestFormatter = new MaintenanceRequestFormatter();
            $maintenanceRequest = $maintenanceRequestFormatter->makeRequest([
                'amount' => $captureAmount,
                'operation' => Operation::CAPTURE,
            ]);

            // Create HiPay capture related to this transaction
            $capture = OrderCaptureEntity::create(
                $maintenanceRequest->operation_id,
                floatval($maintenanceRequest->amount),
                $hipayOrder
            );

            /* @infection-ignore-all */
            $this->logger->info(
                'Payload for Maintenance capture request',
                (array)$maintenanceRequest
            );

            // Make HiPay Maintenance request to capture transaction
            $maintenanceResponse = $clientService
                ->getConfiguredClient($isApplePay)
                ->requestMaintenanceOperation(
                    $maintenanceRequest->operation,
                    $hipayOrder->getTransactionReference(),
                    $maintenanceRequest->amount,
                    $maintenanceRequest->operation_id,
                    $maintenanceRequest
                );

            /* @infection-ignore-all */
            $this->logger->info(
                'Response of Maintenance capture request',
                (array)$maintenanceResponse
            );

            // Save HiPay capture to database
            $this->hipayOrderCaptureRepository->create([$capture->toArray()], $context);

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            /* @infection-ignore-all */
            $this->logger->error($e->getCode() . ' : ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: "/api/_action/hipay/refund")]
    public function refund(RequestDataBag $params, HiPayHttpClientService $clientService, SalesChannelContext $salesChannelContext): JsonResponse
    {
        if (!is_string($params->get('hipayOrder'))) {
            return new JsonResponse(['success' => false, 'message' => 'HiPay Order parameter is mandatory'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $hipayOrderData = json_decode($params->get('hipayOrder'));

            $maintenanceRequestFormatter = new MaintenanceRequestFormatter();
            $maintenanceRequest = $maintenanceRequestFormatter->makeRequest([
                'amount' => $params->get('amount'),
                'operation' => Operation::REFUND,
            ]);

            $context = $salesChannelContext->getContext();

            // Search HiPay order entity by ID
            $hipayOrderCriteria = (new Criteria([$hipayOrderData->id]))
                ->addAssociations(['refunds', 'transaction.paymentMethod'])
                ->setLimit(1);

            /** @var HipayOrderEntity */
            $hipayOrder = $this->hipayOrderRepository->search($hipayOrderCriteria, $context)->getEntities()->first();

            $isApplePay = 'apple_pay' === $hipayOrder->getTransaction()->getPaymentMethod()->getShortName();

            // Create HiPay refund related to this transaction
            $refund = OrderRefundEntity::create(
                $maintenanceRequest->operation_id,
                floatval($maintenanceRequest->amount),
                $hipayOrder
            );

            $this->logger->info('Payload for Maintenance refund request', (array)$maintenanceRequest);

            // Make HiPay Maintenance request to refund transaction
            $maintenanceResponse = $clientService
                ->getConfiguredClient($isApplePay)
                ->requestMaintenanceOperation(
                    $maintenanceRequest->operation,
                    $hipayOrder->getTransactionReference(),
                    $maintenanceRequest->amount,
                    $maintenanceRequest->operation_id,
                    $maintenanceRequest
                );

            $this->logger->info('Response of Maintenance refund request', (array)$maintenanceResponse);

            // Save HiPay refund to database
            $this->hipayOrderRefundRepository->create([$refund->toArray()], $context);

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            /* @infection-ignore-all */
            $this->logger->error($e->getCode() . ' : ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: "/api/_action/hipay/cancel")]
    public function cancel(RequestDataBag $params, HiPayHttpClientService $clientService, SalesChannelContext $salesChannelContext): JsonResponse
    {
        if (!is_string($params->get('hipayOrder'))) {
            return new JsonResponse(['success' => false, 'message' => 'HiPay Order parameter is mandatory'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $hipayOrderData = json_decode($params->get('hipayOrder'));

            $maintenanceRequestFormatter = new MaintenanceRequestFormatter();
            $maintenanceRequest = $maintenanceRequestFormatter->makeRequest([
                'operation' => Operation::CANCEL,
            ]);

            $context = $salesChannelContext->getContext();

            // Search HiPay order entity by ID
            $hipayOrderCriteria = (new Criteria([$hipayOrderData->id]))
                ->addAssociation('transaction.paymentMethod')
                ->setLimit(1);

            /** @var HipayOrderEntity */
            $hipayOrder = $this->hipayOrderRepository->search($hipayOrderCriteria, $context)->getEntities()->first();

            $isApplePay = 'apple_pay' === $hipayOrder->getTransaction()->getPaymentMethod()->getShortName();

            /* @infection-ignore-all */
            $this->logger->info(
                'Payload for Maintenance cancel request',
                (array)$maintenanceRequest
            );

            // Make HiPay Maintenance request to refund transaction
            $maintenanceResponse = $clientService
                ->getConfiguredClient($isApplePay)
                ->requestMaintenanceOperation(
                    $maintenanceRequest->operation,
                    $hipayOrder->getTransactionReference()
                );

            /* @infection-ignore-all */
            $this->logger->info(
                'Response of Maintenance cancel request',
                (array)$maintenanceResponse
            );

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            /* @infection-ignore-all */
            $this->logger->error($e->getCode() . ' : ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Extract Configuration for SimpleHTTPClient from the plugin config data.
     */
    private function extractConfigurationFromPluginConfig(RequestDataBag $params, string $scope): Configuration
    {
        $prefix = HiPayPaymentPlugin::getModuleName() . '.config.';
        $environement = ucfirst($params->getAlpha('environment'));
        $isApplePay = $params->get('isApplePay');

        $login = $isApplePay ? 'ApplePayLogin' : 'Login';
        $password = $isApplePay ? 'ApplePayPassword' : 'Password';
        $payload = [
            HiPayHttpClientService::API_USERNAME => $params->get(
                $prefix
                . $scope
                . $login
                . $environement
            ),
            HiPayHttpClientService::API_PASSWORD => $params->get(
                $prefix
                . $scope
                . $password
                . $environement
            ),
            HiPayHttpClientService::API_ENV => strtolower($environement),
        ];

        /* @infection-ignore-all */
        $this->logger->debug("Payload for $scope $environement", $payload);

        return new Configuration($payload);
    }


    #[Route(path: "/api/_action/hipay/get-logs")]
    public function getHipayLogs(): Response
    {
        try {
            $path = $this->container->get('parameter_bag')->get('kernel.logs_dir') . DIRECTORY_SEPARATOR . 'hipay';

            $zip = new \ZipArchive();
            $zipName = 'hipay-log-' . (new \DateTime())->format('Y-m-d\TH-i-s\Z') . '.zip';

            $zip->open($zipName, \ZipArchive::CREATE);

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($path) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();

            $response = new Response(
                file_get_contents($zipName) ?: null,
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/zip',
                    'Content-Disposition' => 'attachment;filename="' . $zipName . '"',
                    'Content-length' => filesize($zipName) . PHP_EOL,
                ]
            );

            @unlink($zipName);

            return $response;
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
