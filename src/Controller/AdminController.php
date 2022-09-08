<?php declare(strict_types=1);

namespace HiPay\Payment\Controller;

use HiPay\Fullservice\Exception\ApiErrorException;
use HiPay\Fullservice\HTTP\Configuration\Configuration;
use HiPay\Payment\HiPayPaymentPlugin;
use HiPay\Payment\Service\HiPayHttpClientService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"administration"}})
 *
 * Class AdminController
 */
class AdminController
{
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $hipayApiLogger) {
        $this->logger = $hipayApiLogger;
    }

    /**
     * @Route(path="/api/_action/hipay/checkAccess")
     *
     * @param RequestDataBag $dataBag
     * @return JsonResponse
     */
    public function checkAccess(RequestDataBag $params, HiPayHttpClientService $clientService): JsonResponse
    {
        foreach (['public', 'private'] as $scope) {
            try {
                $conf = $this->extractConfigurationFromPluginConfig($params, $scope);

                $client = $clientService->getClient($conf);
                $response = $client->request(Request::METHOD_GET, HiPayHttpClientService::URL_SECURITY_SETTINGS);

                if($response->getStatusCode() !== Response::HTTP_OK) {
                    throw new ApiErrorException($response->getBody());
                }

            } catch (\Throwable $e) {
                $message = 'Error on ' . $scope . ' key : ' . $e->getMessage();
                $this->logger->error($message);
                return new JsonResponse(['success' => false, 'message' => $message]);
            }
        }

        return new JsonResponse(['success' => true, 'message' => 'Access granted']);
    }

    /**
     * Etract Configuration for SimpleHTTPClient from the plugin config data
     * 
     * @param RequestDataBag $params 
     * @param string $scope 
     * 
     * @return Configuration 
     */
    private function extractConfigurationFromPluginConfig(RequestDataBag $params, string $scope): Configuration
    {
        $environement = ucFirst($params->get('environment'));

        return new Configuration([
            'apiUsername' => $params->get(HiPayPaymentPlugin::getModuleName() . '.config.' . $scope . 'Login' . $environement),
            'apiPassword' => $params->get(HiPayPaymentPlugin::getModuleName() . '.config.' . $scope . 'Password' . $environement),
            'apiEnv' => strtolower($environement)
        ]);
    }
}
