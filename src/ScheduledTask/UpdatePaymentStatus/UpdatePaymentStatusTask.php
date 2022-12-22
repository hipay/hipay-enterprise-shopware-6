<?php

namespace HiPay\Payment\ScheduledTask\UpdatePaymentStatus;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class UpdatePaymentStatusTask extends ScheduledTask
{
    /** {@inheritDoc} */
    public static function getTaskName(): string
    {
        return 'hipay.payment.update';
    }

    /** {@inheritDoc} */
    public static function getDefaultInterval(): int
    {
        if ('dev' === getenv('APP_ENV')) {
            return 30;
        }

        return 300; // 5 minutes
    }
}
