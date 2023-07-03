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
            ADD `order_version_id` BINARY(16) NOT NULL,
            ADD `order_transaction_version_id` BINARY(16) NOT NULL;
        SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        $sql = <<<SQL
        ALTER TABLE `hipay_order`
            DROP `order_version_id`,
            DROP `order_transaction_version_id`;
        SQL;
        $connection->executeStatement($sql);
    }
}
