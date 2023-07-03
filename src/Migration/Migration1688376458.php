<?php

declare(strict_types=1);

namespace HiPay\Payment\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1688376458 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1688376458;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
        ALTER TABLE `hipay_order`
            ADD `order_version_id` BINARY(16) UNIQUE NOT NULL,
            ADD `transaction_version_id` BINARY(16) UNIQUE NOT NULL,
            DROP CONSTRAINT `fk.hipay_order.order_id`,
            DROP CONSTRAINT `fk.hipay_order.transaction_id`;
        SQL;
        $connection->executeStatement($sql);

        $sql = <<<SQL
        ALTER TABLE `hipay_order`
            ADD CONSTRAINT `fk.hipay_order.order_id` FOREIGN KEY (`order_id`, `order_version_id`)
            REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
            ADD CONSTRAINT `fk.hipay_order.transaction_id` FOREIGN KEY (`transaction_id`, `transaction_version_id`)
            REFERENCES `order_transaction` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE;
        SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        $sql = <<<SQL
        ALTER TABLE `hipay_order`
            DROP `order_version_id`,
            DROP `transaction_version_id`,
            DROP CONSTRAINT `fk.hipay_order.order_id`,
            DROP CONSTRAINT `fk.hipay_order.transaction_id`;
        SQL;
        $connection->executeStatement($sql);

        $sql = <<<SQL
        ALTER TABLE `hipay_order`
            ADD CONSTRAINT `fk.hipay_order.order_id` FOREIGN KEY (`order_id`)
            REFERENCES `order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            ADD CONSTRAINT `fk.hipay_order.transaction_id` FOREIGN KEY (`transaction_id`)
            REFERENCES `order_transaction` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        SQL;
        $connection->executeStatement($sql);
    }
}
