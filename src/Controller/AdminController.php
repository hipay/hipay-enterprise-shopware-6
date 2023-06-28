<?php

namespace HiPay\Payment\Controller;

use HiPay\Fullservice\Enum\Transaction\Operation;
use HiPay\Fullservice\HTTP\Configuration\Configuration;
use HiPay\Payment\Core\Checkout\Payment\Capture\OrderCaptureEntity;
use HiPay\Payment\Core\Checkout\Payment\HipayOrder\HipayOrderEntity;
use HiPay\Payment\Core\Checkout\Payment\Refund\OrderRefundEntity;
use HiPay\Payment\Formatter\Request\MaintenanceRequestFormatter;
use HiPay\Payment\HiPayPaymentPlugin;
use HiPay\Payment\Logger\HipayLogger;
use HiPay\Payment\Service\HiPayHttpClientService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Exception\InvalidParameterException;

/**
 * @Route(defaults={"_routeScope"={"administration"}})
 *
 * Class AdminController
 */
class AdminController extends AbstractController
{
    protected LoggerInterface $logger;

    private EntityRepository $hipayOrderRepo;

    private EntityRepository $hipayOrderCaptureRepo;

    private EntityRepository $hipayOrderRefundRepo;

    /**
     * Constructor.
     */
    public function __construct(
        EntityRepository $hipayOrderRepository,
        EntityRepository $hipayOrderCaptureRepository,
        EntityRepository $hipayOrderRefundRepository,
        HipayLogger $hipayLogger
    ) {
        $this->hipayOrderRepo = $hipayOrderRepository;
        $this->hipayOrderCaptureRepo = $hipayOrderCaptureRepository;
        $this->hipayOrderRefundRepo = $hipayOrderRefundRepository;
        $this->logger = $hipayLogger->setChannel(HipayLogger::API);
    }

    /**
     * @Route(path="/api/_action/hipay/checkAccess")
     */
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
                $message = "Error on $scope key : ".$e->getMessage();

                /* @infection-ignore-all */
                $this->logger->error($message);

                return new JsonResponse([
                    'success' => false,
                    'message' => $message,
                ]);
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Access granted',
        ]);
    }

    /**
     * @Route(path="/api/_action/hipay/capture")
     */
    public function capture(RequestDataBag $params, HiPayHttpClientService $clientService): JsonResponse
    {
        try {
            if (!is_string($params->get('hipayOrder'))) {
                throw new JsonException('HiPay Order parameter is mandatory');
            }

            $hipayOrderData = json_decode($params->get('hipayOrder'));
            $captureAmount = floatval($params->get('amount'));

            $context = Context::createDefaultContext();

            // Search HiPay order entity by ID
            $hipayOrderCriteria = new Criteria([$hipayOrderData->id]);
            $hipayOrderCriteria->addAssociations(['captures', 'transaction.paymentMethod']);
            /** @var HipayOrderEntity */
            $hipayOrder = $this->hipayOrderRepo->search($hipayOrderCriteria, $context)->first();

            $config = $hipayOrder->getTransaction()->getPaymentMethod()->getExtension('hipayConfig');
            $totalTransaction = $hipayOrder->getTransaction()->getAmount()->getTotalPrice();

            // @phpstan-ignore-next-line
            if (!boolval($config['allowPartialCapture']) && $captureAmount !== $totalTransaction) {
                throw new InvalidParameterException('Only the full capture is allowed');
            }

            $maintenanceRequestFormatter = new MaintenanceRequestFormatter();
            $maintenanceRequest = $maintenanceRequestFormatter->makeRequest([
                'amount' => $captureAmount,
                'operation' => Operation::CAPTURE,
            ]);

            // Create HiPay capture related to this transaction
            $capture = OrderCaptureEntity::create($maintenanceRequest->operation_id, floatval($maintenanceRequest->amount), $hipayOrder);

            /* @infection-ignore-all */
            $this->logger->info(
                'Payload for Maintenance capture request',
                (array) $maintenanceRequest
            );

            // Make HiPay Maintenance request to capture transaction
            $maintenanceResponse = $clientService
                ->getConfiguredClient()
                ->requestMaintenanceOperation(
                    $maintenanceRequest->operation,
                    $hipayOrder->getTransanctionReference(),
                    $maintenanceRequest->amount,
                    $maintenanceRequest->operation_id,
                    $maintenanceRequest
                );

            /* @infection-ignore-all */
            $this->logger->info(
                'Response of Maintenance capture request',
                (array) $maintenanceResponse
            );

            // Save HiPay capture to database
            $this->hipayOrderCaptureRepo->create([$capture->toArray()], $context);

            return new JsonResponse([
                'success' => true,
                'captures' => $hipayOrder->getCapturesToArray() + [$capture],
                'captured_amount' => $hipayOrder->getCapturedAmount() + $capture->getAmount(),
            ]);
        } catch (\Exception $e) {
            /* @infection-ignore-all */
            $this->logger->error($e->getCode().' : '.$e->getMessage());

            return new JsonResponse(['success' => false]);
        }
    }

    /**
     * @Route(path="/api/_action/hipay/refund")
     */
    public function refund(RequestDataBag $params, HiPayHttpClientService $clientService): JsonResponse
    {
        try {
            if (!is_string($params->get('hipayOrder'))) {
                throw new JsonException('HiPay Order parameter is mandatory');
            }

            $hipayOrderData = json_decode($params->get('hipayOrder'));

            $maintenanceRequestFormatter = new MaintenanceRequestFormatter();
            $maintenanceRequest = $maintenanceRequestFormatter->makeRequest([
                'amount' => $params->get('amount'),
                'operation' => Operation::REFUND,
            ]);

            $context = Context::createDefaultContext();

            // Search HiPay order entity by ID
            $hipayOrderCriteria = new Criteria([$hipayOrderData->id]);
            $hipayOrderCriteria->addAssociation('refunds');
            /** @var HipayOrderEntity */
            $hipayOrder = $this->hipayOrderRepo->search($hipayOrderCriteria, $context)->first();

            // Create HiPay refund related to this transaction
            $refund = OrderRefundEntity::create($maintenanceRequest->operation_id, floatval($maintenanceRequest->amount), $hipayOrder);

            /* @infection-ignore-all */
            $this->logger->info(
                'Payload for Maintenance refund request',
                (array) $maintenanceRequest
            );

            // Make HiPay Maintenance request to refund transaction
            $maintenanceResponse = $clientService
                ->getConfiguredClient()
                ->requestMaintenanceOperation(
                    $maintenanceRequest->operation,
                    $hipayOrder->getTransanctionReference(),
                    $maintenanceRequest->amount,
                    $maintenanceRequest->operation_id,
                    $maintenanceRequest
                );

            /* @infection-ignore-all */
            $this->logger->info(
                'Response of Maintenance refund request',
                (array) $maintenanceResponse
            );

            // Save HiPay refund to database
            $this->hipayOrderRefundRepo->create([$refund->toArray()], $context);

            return new JsonResponse([
                'success' => true,
                'refunds' => $hipayOrder->getRefundsToArray() + [$refund],
                'refunded_amount' => $hipayOrder->getRefundedAmount() + $refund->getAmount(),
            ]);
        } catch (\Exception $e) {
            /* @infection-ignore-all */
            $this->logger->error($e->getCode().' : '.$e->getMessage());

            return new JsonResponse(['success' => false]);
        }
    }

    /**
     * @Route(path="/api/_action/hipay/cancel")
     */
    public function cancel(RequestDataBag $params, HiPayHttpClientService $clientService): JsonResponse
    {
        try {
            if (!is_string($params->get('hipayOrder'))) {
                throw new JsonException('HiPay Order parameter is mandatory');
            }

            $hipayOrderData = json_decode($params->get('hipayOrder'));

            $maintenanceRequestFormatter = new MaintenanceRequestFormatter();
            $maintenanceRequest = $maintenanceRequestFormatter->makeRequest([
                'operation' => Operation::CANCEL,
            ]);

            $context = Context::createDefaultContext();

            // Search HiPay order entity by ID
            $hipayOrderCriteria = new Criteria([$hipayOrderData->id]);
            /** @var HipayOrderEntity */
            $hipayOrder = $this->hipayOrderRepo->search($hipayOrderCriteria, $context)->first();

            /* @infection-ignore-all */
            $this->logger->info(
                'Payload for Maintenance cancel request',
                (array) $maintenanceRequest
            );

            // Make HiPay Maintenance request to refund transaction
            $maintenanceResponse = $clientService
                ->getConfiguredClient()
                ->requestMaintenanceOperation(
                    $maintenanceRequest->operation,
                    $hipayOrder->getTransanctionReference()
                );

            /* @infection-ignore-all */
            $this->logger->info(
                'Response of Maintenance cancel request',
                (array) $maintenanceResponse
            );

            return new JsonResponse([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            /* @infection-ignore-all */
            $this->logger->error($e->getCode().' : '.$e->getMessage());

            return new JsonResponse(['success' => false]);
        }
    }

    /**
     * Extract Configuration for SimpleHTTPClient from the plugin config data.
     */
    private function extractConfigurationFromPluginConfig(RequestDataBag $params, string $scope): Configuration
    {
        $environement = ucfirst($params->getAlpha('environment'));

        $payload = [
            HiPayHttpClientService::API_USERNAME => $params->get(
                HiPayPaymentPlugin::getModuleName()
                    .'.config.'
                    .$scope
                    .'Login'
                    .$environement
            ),
            HiPayHttpClientService::API_PASSWORD => $params->get(
                HiPayPaymentPlugin::getModuleName()
                    .'.config.'
                    .$scope
                    .'Password'
                    .$environement
            ),
            HiPayHttpClientService::API_ENV => strtolower($environement),
        ];

        /* @infection-ignore-all */
        $this->logger->debug("Payload for $scope $environement", $payload);

        return new Configuration($payload);
    }

    /**
     * @Route(path="/api/_action/hipay/get-logs")
     */
    public function getHipayLogs(): Response
    {
        try {
            $path = $this->container->get('parameter_bag')->get('kernel.logs_dir').DIRECTORY_SEPARATOR.'hipay';

            $zip = new \ZipArchive();
            $zipName = 'hipay-log-'.(new \DateTime())->format('Y-m-d\TH-i-s\Z').'.zip';

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
                200,
                [
                    'Content-Type' => 'application/zip',
                    'Content-Disposition' => 'attachment;filename="'.$zipName.'"',
                    'Content-length' => filesize($zipName).PHP_EOL,
                ]
            );

            @unlink($zipName);

            return $response;
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
