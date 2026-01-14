<?php

declare(strict_types=1);

namespace SquarePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1760000100SquareSubscriptionCardChoice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1760000100;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
            CREATE TABLE IF NOT EXISTS `square_subscription_card_choice` (
                `id`              BINARY(16)   NOT NULL,
                `customer_id`     BINARY(16)   NOT NULL,
                `subscription_id` CHAR(32)     NOT NULL,
                `square_card_id`  VARCHAR(255) NOT NULL,
                `created_at`      DATETIME(3)  NOT NULL,
                `updated_at`      DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `uniq.square_subscription_card_choice.customer_subscription` UNIQUE (`customer_id`, `subscription_id`),
                CONSTRAINT `fk.square_subscription_card_choice.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
