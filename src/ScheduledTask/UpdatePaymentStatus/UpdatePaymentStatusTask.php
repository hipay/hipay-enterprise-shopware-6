<?php

namespace HiPay\Payment\ScheduledTask\UpdatePaymentStatus;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class UpdatePaymentStatusTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'hipay.payment.update';
    }

    public static function getDefaultInterval(): int
    {
        if ('dev' === getenv('APP_ENV')) {
            return 30;
        }

        return 300; // 5 minutes
    }
}
