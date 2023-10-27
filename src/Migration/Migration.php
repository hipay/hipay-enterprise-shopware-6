<?php

namespace HiPay\Payment\Migration;

use Doctrine\DBAL\Connection;
use HiPay\Payment\Enum\CaptureStatus;
use HiPay\Payment\Enum\RefundStatus;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1665643540;
    }

    public function update(Connection $connection): void
    {
        // Create table to save HiPay details related to a Shopware transaction
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `hipay_order` (
            `id` BINARY(16) NOT NULL,
            `transaction_reference` VARCHAR(255) UNIQUE NOT NULL,
            `order_id` BINARY(16) UNIQUE NOT NULL,
            `transaction_id` BINARY(16) UNIQUE NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk.hipay_order.order_id` FOREIGN KEY (`order_id`)
            REFERENCES `order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.hipay_order.transaction_id` FOREIGN KEY (`transaction_id`)
            REFERENCES `order_transaction` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);

        // Create table for HiPay notifications
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `hipay_notification` (
            `id` BINARY(16) NOT NULL,
            `status` INT(4) NOT NULL,
            `data` JSON NOT NULL,
            `notification_updated_at` DATETIME(3) NOT NULL,
            `hipay_order_id` BINARY(16) NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk.hipay_notification.hipay_order_id` FOREIGN KEY (`hipay_order_id`)
            REFERENCES `hipay_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        )
        ENGINE = InnoDB
        DEFAULT CHARSET = utf8mb4
        COLLATE = utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);

        // Create table for HiPay captures related to order transaction
        $defaultCapture = CaptureStatus::OPEN;
        $reflectionClass = new \ReflectionClass(CaptureStatus::class);
        $constants = implode("','", array_flip($reflectionClass->getConstants()));

        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `hipay_order_capture` (
            `id` BINARY(16) NOT NULL,
            `operation_id` VARCHAR(36) UNIQUE NOT NULL,
            `amount` FLOAT NOT NULL,
            `status` ENUM('{$constants}') NOT NULL DEFAULT '{$defaultCapture}',
            `hipay_order_id` BINARY(16) NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk.hipay_order_capture.hipay_order_id` FOREIGN KEY (`hipay_order_id`)
            REFERENCES `hipay_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        )
        ENGINE = InnoDB
        DEFAULT CHARSET = utf8mb4
        COLLATE = utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);

        // Create table for HiPay refunds related to order transaction
        $defaultRefund = RefundStatus::OPEN;
        $reflectionClass = new \ReflectionClass(RefundStatus::class);
        $constants = implode("','", array_flip($reflectionClass->getConstants()));

        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `hipay_order_refund` (
            `id` BINARY(16) NOT NULL,
            `operation_id` VARCHAR(36) UNIQUE NOT NULL,
            `amount` FLOAT NOT NULL,
            `status` ENUM('{$constants}') NOT NULL DEFAULT '{$defaultRefund}',
            `hipay_order_id` BINARY(16) NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk.hipay_order_refund.hipay_order_id` FOREIGN KEY (`hipay_order_id`)
            REFERENCES `hipay_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        )
        ENGINE = InnoDB
        DEFAULT CHARSET = utf8mb4
        COLLATE = utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);

        // Create table for HiPay status related to hipay order
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `hipay_status_flow` (
            `id` BINARY(16) NOT NULL,
            `hipay_order_id` BINARY(16) NOT NULL,
            `code` INT NOT NULL,
            `message` VARCHAR(255),
            `amount` FLOAT NOT NULL,
            `hash` CHAR(8) UNIQUE NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk.hipay_status_flow.hipay_order_id` FOREIGN KEY (`hipay_order_id`)
            REFERENCES `hipay_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        )
        ENGINE = InnoDB
        DEFAULT CHARSET = utf8mb4
        COLLATE = utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);

        // Create table for credit card token
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `hipay_card_token` (
            `id` BINARY(16) NOT NULL,
            `customer_id` BINARY(16) NOT NULL,
            `token` CHAR(64) NOT NULL,
            `brand` VARCHAR(16) NOT NULL,
            `pan` CHAR(16) NOT NULL,
            `card_holder` VARCHAR(255) NOT NULL,
            `card_expiry_month` VARCHAR(2) NOT NULL,
            `card_expiry_year` VARCHAR(4) NOT NULL,
            `issuer` VARCHAR(255) NOT NULL,
            `country` CHAR(2) NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk.hipay_card_token.customer_id` FOREIGN KEY (`customer_id`)
            REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        )
        ENGINE = InnoDB
        DEFAULT CHARSET = utf8mb4
        COLLATE = utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Preserve HiPay tables
    }
}
