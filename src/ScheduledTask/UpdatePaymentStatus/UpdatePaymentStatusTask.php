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
        return 300; // 5 minutes
    }
}
