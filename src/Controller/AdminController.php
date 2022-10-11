<?php

declare(strict_types=1);

namespace HiPay\Payment\Controller;

use HiPay\Fullservice\HTTP\Configuration\Configuration;
use HiPay\Payment\HiPayPaymentPlugin;
use HiPay\Payment\Service\HiPayHttpClientService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"administration"}})
 *
 * Class AdminController
 */
class AdminController
{
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $hipayApiLogger)
    {
        $this->logger = $hipayApiLogger;
    }

    /**
     * @Route(path="/api/_action/hipay/checkAccess")
     */
    public function checkAccess(RequestDataBag $params, HiPayHttpClientService $clientService): JsonResponse
    {
        foreach (['public', 'private'] as $scope) {
            try {
                $conf = $this->extractConfigurationFromPluginConfig($params, $scope);

                $clientService->getClient($conf)->requestSecuritySettings();
            } catch (\Exception $e) {
                $message = 'Error on '.$scope.' key : '.$e->getMessage();

                /* @infection-ignore-all */
                $this->logger->error($message);

                return new JsonResponse(['success' => false, 'message' => $message]);
            }
        }

        return new JsonResponse(['success' => true, 'message' => 'Access granted']);
    }

    /**
     * Extract Configuration for SimpleHTTPClient from the plugin config data.
     */
    private function extractConfigurationFromPluginConfig(RequestDataBag $params, string $scope): Configuration
    {
        $environement = ucfirst($params->getAlpha('environment'));

        $payload = [
            HiPayHttpClientService::API_USERNAME => $params->get(HiPayPaymentPlugin::getModuleName().'.config.'.$scope.'Login'.$environement),
            HiPayHttpClientService::API_PASSWORD => $params->get(HiPayPaymentPlugin::getModuleName().'.config.'.$scope.'Password'.$environement),
            HiPayHttpClientService::API_ENV => strtolower($environement),
        ];

        /* @infection-ignore-all */
        $this->logger->debug('Payload for '.$scope.' '.$environement, $payload);

        return new Configuration($payload);
    }
}
