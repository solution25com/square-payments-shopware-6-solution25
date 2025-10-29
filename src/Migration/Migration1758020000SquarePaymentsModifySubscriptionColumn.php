<?php

declare(strict_types=1);

namespace SquarePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1758020000SquarePaymentsModifySubscriptionColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1758020000;
    }

    public function update(Connection $connection): void
    {
        $subscriptionCardExists = (int) $connection->fetchOne(<<<SQL
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'squarepayments_transaction'
              AND COLUMN_NAME = 'subscription_card';
        SQL);

        if ($subscriptionCardExists === 0) {
            $connection->executeStatement(<<<SQL
                ALTER TABLE `squarepayments_transaction`
                ADD COLUMN `subscription_card` JSON NULL AFTER `is_subscription`;
            SQL);
        }

        // Migrate legacy column data if present
        $legacyExists = (int) $connection->fetchOne(<<<SQL
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'squarepayments_transaction'
              AND COLUMN_NAME = 'subscription_transaction_id';
        SQL);

        if ($legacyExists > 0) {
              $connection->executeStatement(<<<SQL
                 ALTER TABLE `squarepayments_transaction`
                 DROP COLUMN `subscription_transaction_id`;
                SQL);
        }
    }
}
