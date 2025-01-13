<?php

namespace HiPay\Payment\Controller;

use HiPay\Payment\Enum\HipayLoggerChannel;
use HiPay\Payment\Service\NotificationService;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller use to receive notifications from Hipay.
 */
#[WithMonologChannel(HipayLoggerChannel::API)]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => false])]
class NotificationController
{

    public function __construct(
        private LoggerInterface $logger,
        private NotificationService $notificationService
    ) {}

    #[Route('/api/hipay/notify', name: 'store-api.action.hipay.notification', methods: ['POST', 'GET'])]
    public function receiveNotification(Request $request, SalesChannelContext $context): JsonResponse
    {
        try {
            $this->notificationService->saveNotificationRequest($request, $context->getContext());
        } catch (\Throwable $e) {
            $message = 'Notification fail : ' . $e->getMessage();
            $this->logger->error($message);

            $code = Response::HTTP_INTERNAL_SERVER_ERROR;
            if ($e instanceof UnauthorizedHttpException) {
                $code = Response::HTTP_UNAUTHORIZED;
            } elseif ($e instanceof AccessDeniedException) {
                $code = Response::HTTP_FORBIDDEN;
            }

            return new JsonResponse(['success' => false, 'error' => $message], $code);
        }

        return new JsonResponse(['success' => true]);
    }
}
