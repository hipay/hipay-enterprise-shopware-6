<?php

declare(strict_types=1);

namespace HiPay\Payment\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep as ShopwareMigrationStep;

abstract class MigrationStep extends ShopwareMigrationStep
{
    protected function constraintExists(Connection $connection, string $referencedTable, string $constraint): bool
    {
        $schema = $connection->getDatabase();
        $sql = <<<SQL
            SELECT CONSTRAINT_NAME
                FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = '{$schema}'
                AND REFERENCED_TABLE_NAME = '{$referencedTable}'
                AND CONSTRAINT_NAME = '{$constraint}';
        SQL;

        $exists = $connection->fetchOne($sql);

        return !empty($exists);
    }
}
