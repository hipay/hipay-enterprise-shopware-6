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
        $table = 'hipay_order';

        if(!$this->columnExists($connection, $table, 'order_version_id')) {
            $sql = <<<SQL
            ALTER TABLE `hipay_order`
                ADD `order_version_id` BINARY(16) NOT NULL;
            SQL;
            $connection->executeStatement($sql);
        }

        if(!$this->columnExists($connection, $table, 'transaction_version_id')) {
            $sql = <<<SQL
            ALTER TABLE `hipay_order`
                ADD `transaction_version_id` BINARY(16) NOT NULL;
            SQL;
            $connection->executeStatement($sql);
        }

        if($this->indexExists($connection, $table, 'fk.hipay_order.order_id')) {
            $sql = <<<SQL
            ALTER TABLE `hipay_order`
                DROP FOREIGN KEY `fk.hipay_order.order_id`;
            ALTER TABLE `hipay_order`
                ADD UNIQUE KEY `fk.hipay_order.order_id` (`order_id`,`order_version_id`),
                ADD CONSTRAINT `fk.hipay_order.order_id` FOREIGN KEY (`order_id`, `order_version_id`)
                REFERENCES `order` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE;
            SQL;
            $connection->executeStatement($sql);
        }

        if($this->indexExists($connection, $table, 'fk.hipay_order.transaction_id')) {
            $sql = <<<SQL
            ALTER TABLE `hipay_order`
                DROP FOREIGN KEY `fk.hipay_order.transaction_id`;
            ALTER TABLE `hipay_order`
                ADD UNIQUE KEY `fk.hipay_order.transaction_id` (`transaction_id`,`transaction_version_id`),
                ADD CONSTRAINT `fk.hipay_order.transaction_id` FOREIGN KEY (`transaction_id`, `transaction_version_id`)
                REFERENCES `order_transaction` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE;
            SQL;
            $connection->executeStatement($sql);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
