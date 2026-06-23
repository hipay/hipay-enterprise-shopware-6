<?php

declare(strict_types=1);

namespace HiPay\Payment\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1778145751RemoveKlarna extends MigrationStep
{
    private const KLARNA_HANDLER = 'HiPay\\Payment\\PaymentMethod\\Klarna';

    public function getCreationTimestamp(): int
    {
        return 1778145751;
    }

    public function update(Connection $connection): void
    {
        $id = $connection->fetchOne(
            'SELECT id FROM payment_method WHERE handler_identifier = :handler LIMIT 1',
            ['handler' => self::KLARNA_HANDLER]
        );

        if (!$id) {
            return;
        }

        $usedInOrders = $connection->fetchOne(
            'SELECT COUNT(*) FROM order_transaction WHERE payment_method_id = :id',
            ['id' => $id]
        );

        if ($usedInOrders > 0) {
            $connection->executeStatement(
                'UPDATE payment_method SET active = 0 WHERE id = :id',
                ['id' => $id]
            );
            return;
        }

        $connection->executeStatement(
            'DELETE FROM payment_method_translation WHERE payment_method_id = :id',
            ['id' => $id]
        );
        $connection->executeStatement(
            'DELETE FROM sales_channel_payment_method WHERE payment_method_id = :id',
            ['id' => $id]
        );
        $connection->executeStatement(
            'DELETE FROM payment_method WHERE id = :id',
            ['id' => $id]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
