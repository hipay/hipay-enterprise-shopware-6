<?php

namespace Hipay\Payment\Tests\Unit\Controller;

use Exception;
use HiPay\Payment\Controller\NotificationController;
use HiPay\Payment\Service\NotificationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class NotificationControllerTest extends TestCase
{
    public function testReceiveNotificationSuccess()
    {
        $service = $this->createMock(NotificationService::class);

        $controller = new NotificationController(
            new NullLogger(),
            $service
        );

        $response = $controller->receiveNotification(new Request());

        $this->assertSame(
            json_encode(['success' => true]),
            $response->getContent()
        );
    }

    public function testReceiveNotificationUnauthorized()
    {
        /** @var NotificationService&MockObject */
        $service = $this->createMock(NotificationService::class);
        $service->method('saveNotificationRequest')->willThrowException(new UnauthorizedHttpException('', 'FOO'));

        $controller = new NotificationController(
            new NullLogger(),
            $service
        );

        $response = $controller->receiveNotification(new Request());

        $this->assertEquals(
            401,
            $response->getStatusCode()
        );

        $this->assertSame(
            json_encode(['success' => false, 'error' => 'Notification fail : FOO']),
            $response->getContent()
        );
    }

    public function testReceiveNotificationAccessDenied()
    {
        /** @var NotificationService&MockObject */
        $service = $this->createMock(NotificationService::class);
        $service->method('saveNotificationRequest')->willThrowException(new AccessDeniedException('BAR'));

        $controller = new NotificationController(
            new NullLogger(),
            $service
        );

        $response = $controller->receiveNotification(new Request());

        $this->assertEquals(
            403,
            $response->getStatusCode()
        );

        $this->assertSame(
            json_encode(['success' => false, 'error' => 'Notification fail : BAR']),
            $response->getContent()
        );
    }

    public function testReceiveNotificatioGenericError()
    {
        /** @var NotificationService&MockObject */
        $service = $this->createMock(NotificationService::class);
        $service->method('saveNotificationRequest')->willThrowException(new Exception('QUZ'));

        $controller = new NotificationController(
            new NullLogger(),
            $service
        );

        $response = $controller->receiveNotification(new Request());

        $this->assertEquals(
            500,
            $response->getStatusCode()
        );

        $this->assertSame(
            json_encode(['success' => false, 'error' => 'Notification fail : QUZ']),
            $response->getContent()
        );
    }
}
