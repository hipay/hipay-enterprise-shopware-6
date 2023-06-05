<?php

declare(strict_types=1);

namespace HiPay\Payment\Migration;

use Doctrine\DBAL\Connection;
use HiPay\Payment\Enum\CaptureStatus;
use HiPay\Payment\Enum\RefundStatus;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1685710253_1_0_3 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1685710253;
    }

    public function update(Connection $connection): void
    {
        // Update table for HiPay captures - Remove ENUM
        $defaultCapture = CaptureStatus::OPEN;

        $sql = <<<SQL
        ALTER TABLE `hipay_order_capture`
            MODIFY `status` VARCHAR(36) NOT NULL DEFAULT '{$defaultCapture}';
        SQL;
        $connection->executeStatement($sql);

        // Update table for HiPay refunds - Remove ENUM
        $defaultRefund = RefundStatus::OPEN;

        $sql = <<<SQL
        ALTER TABLE `hipay_order_refund`
            MODIFY `status` VARCHAR(36) NOT NULL DEFAULT '{$defaultRefund}';
        SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
