<?php

declare(strict_types=1);

namespace HiPay\Payment\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Przelewy24 payment method is now displayed as "Blik / Przelewy24".
 */
class Migration1790812800RenamePrzelewy24 extends MigrationStep
{
    private const HANDLER = 'HiPay\\Payment\\PaymentMethod\\Przelewy24';
    private const OLD_NAME = 'Przelewy24';
    private const NEW_NAME = 'Blik / Przelewy24';

    public function getCreationTimestamp(): int
    {
        return 1790812800;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE payment_method_translation
             SET name = :newName
             WHERE name = :oldName
               AND payment_method_id IN (SELECT id FROM payment_method WHERE handler_identifier = :handler)',
            ['newName' => self::NEW_NAME, 'oldName' => self::OLD_NAME, 'handler' => self::HANDLER]
        );

        $connection->executeStatement(
            'UPDATE payment_method_translation
             SET distinguishable_name = CONCAT(:newName, SUBSTRING(distinguishable_name, CHAR_LENGTH(:oldName) + 1))
             WHERE distinguishable_name LIKE :oldPrefix
               AND payment_method_id IN (SELECT id FROM payment_method WHERE handler_identifier = :handler)',
            [
                'newName' => self::NEW_NAME,
                'oldName' => self::OLD_NAME,
                'oldPrefix' => self::OLD_NAME . ' |%',
                'handler' => self::HANDLER,
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
