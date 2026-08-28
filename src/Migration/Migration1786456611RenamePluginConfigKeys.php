<?php

declare(strict_types=1);

namespace HiPay\Payment\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * The plugin's base class was renamed from HiPayPaymentPlugin to HiPayPayments
 * (to match the technical plugin name required by the Shopware Store)
 */
class Migration1786456611RenamePluginConfigKeys extends MigrationStep
{
    private const OLD_PREFIX = 'HiPayPaymentPlugin.config.';
    private const NEW_PREFIX = 'HiPayPayments.config.';

    public function getCreationTimestamp(): int
    {
        return 1786456611;
    }

    public function update(Connection $connection): void
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT id, configuration_key, sales_channel_id FROM system_config WHERE configuration_key LIKE :prefix',
            ['prefix' => self::OLD_PREFIX . '%']
        );

        foreach ($rows as $row) {
            $newKey = self::NEW_PREFIX . substr($row['configuration_key'], strlen(self::OLD_PREFIX));

            $existing = $connection->fetchOne(
                'SELECT id FROM system_config WHERE configuration_key = :key AND (sales_channel_id <=> :salesChannelId)',
                ['key' => $newKey, 'salesChannelId' => $row['sales_channel_id']]
            );

            if ($existing) {
                // A value already exists under the new key (e.g. migration ran before), keep it and drop the old one.
                $connection->executeStatement('DELETE FROM system_config WHERE id = :id', ['id' => $row['id']]);
                continue;
            }

            $connection->executeStatement(
                'UPDATE system_config SET configuration_key = :newKey WHERE id = :id',
                ['newKey' => $newKey, 'id' => $row['id']]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
