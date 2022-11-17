<?php

namespace HiPay\Payment\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1665643540;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `hipay_notification` (
            `id` BINARY(16) NOT NULL,
            `status` INT(4) NOT NULL,
            `data` JSON NOT NULL,
            `notification_updated_at` DATETIME(3) NOT NULL,
            `order_transaction_id` BINARY(16) NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NOT NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk.hipay_notification.order_transaction_id` FOREIGN KEY (`order_transaction_id`)
            REFERENCES `order_transaction` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        )
        ENGINE = InnoDB
        DEFAULT CHARSET = utf8mb4
        COLLATE = utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
