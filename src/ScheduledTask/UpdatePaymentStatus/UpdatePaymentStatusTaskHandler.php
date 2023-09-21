<?php

namespace HiPay\Payment\ScheduledTask\UpdatePaymentStatus;

use HiPay\Payment\Service\NotificationService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class UpdatePaymentStatusTaskHandler extends ScheduledTaskHandler
{
    private NotificationService $notificationService;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        NotificationService $notificationService
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->notificationService = $notificationService;
    }

    /**
     * @return string[]
     */
    public static function getHandledMessages(): iterable
    {
        return [UpdatePaymentStatusTask::class];
    }

    public function run(): void
    {
        $this->notificationService->dispatchNotifications();
    }
}
