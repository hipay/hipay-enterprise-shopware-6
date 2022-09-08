<?php
namespace Hipay\Payment\Tests\Unit\Controller;

use HiPay\Fullservice\HTTP\Response\Response;
use HiPay\Fullservice\HTTP\SimpleHTTPClient;
use HiPay\Payment\Controller\AdminController;
use HiPay\Payment\HiPayPaymentPlugin;
use HiPay\Payment\Service\HiPayHttpClientService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

class AdminControllerTest extends TestCase{

    private function generateRequestDataBag(array $params = [], string $env = 'Production'){

        if(!isset($params['environment'])) {            
            $params = ['environment' => $env];
        }

        foreach(['public', 'private'] as $scope) {
            foreach(['Login', 'Password'] as $field) {        
                $key = HiPayPaymentPlugin::getModuleName() . '.config.' . $scope . $field . $env;
                if(!isset($params[$key])) {
                    $params[$key] = $key;
                }
            }
        }
    
        /** @var RequestDataBag&MockObject */
        $bag = $this->createMock(RequestDataBag::class);
        $bag->method('get')->willReturnCallback(
            function($key, $default = null) use ($params) {
                return $params[$key] ?? $default;
            }
        );

        return $bag;
    }

    private function generateClientService(array $responses) {

        $clients = [];

        foreach($responses as $response) {
            /** @var SimpleHTTPClient&MockObject */
            $client = $this->createMock(SimpleHTTPClient::class);
            $client->method('request')->willReturn($response);

            $clients[] = $client;
        }
        

        /** @var HiPayHttpClientService&MockObject */
        $clientService = $this->createMock(HiPayHttpClientService::class);
        $clientService->method('getClient')->willReturnOnConsecutiveCalls(...$clients);

        return $clientService;
    }

    public function testCheckAccessValid() {
        $responses = [
            new Response('', 200, []),
            new Response('', 200, []),
        ];

        $service = new AdminController(new NullLogger);

        $jsonResponse = json_decode(            
            $service->checkAccess(
                $this->generateRequestDataBag(),
                $this->generateClientService($responses)
            )->getContent()
        , true);

        $this->assertSame(
            ['success' => true, 'message' => 'Access granted'],
            $jsonResponse
        );
    }

    public function testCheckAccessInvalidPublic() {
        $responses = [
            new Response('Foo', 500, []),
            new Response('', 200, []),
        ];

        $service = new AdminController(new NullLogger);

        $jsonResponse = json_decode(            
            $service->checkAccess(
                $this->generateRequestDataBag(),
                $this->generateClientService($responses)
            )->getContent()
        , true);

        $this->assertSame(
            ['success' => false, 'message' => 'Error on public key : Foo' . PHP_EOL],
            $jsonResponse
        );
    }

    public function testCheckAccessInvalidPrivate() {
        $responses = [
            new Response('', 200, []),
            new Response('Bar', 404, []),
        ];

        $service = new AdminController(new NullLogger);

        $jsonResponse = json_decode(            
            $service->checkAccess(
                $this->generateRequestDataBag(),
                $this->generateClientService($responses)
            )->getContent()
        , true);

        $this->assertSame(
            ['success' => false, 'message' => 'Error on private key : Bar' . PHP_EOL],
            $jsonResponse
        );
    }

    public function testCheckAccessInvalidConfig() {
        $responses = [null];

        $service = new AdminController(new NullLogger);

        $jsonResponse = json_decode(            
            $service->checkAccess(
                $this->generateRequestDataBag([], 'Foobar'),
                $this->generateClientService($responses)
            )->getContent()
        );

        $this->assertFalse($jsonResponse->success);
    }
}