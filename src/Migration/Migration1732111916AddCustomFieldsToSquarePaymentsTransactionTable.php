<?php

declare(strict_types=1);

namespace SquarePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1732111916AddCustomFieldsToSquarePaymentsTransactionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1732111916;
    }

    public function update(Connection $connection): void
    {
        // Only add the column if it does not exist
        $schemaManager = $connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('squarepayments_transaction');
        if (!array_key_exists('custom_fields', $columns)) {
            $connection->executeStatement('ALTER TABLE `squarepayments_transaction` ADD COLUMN `custom_fields` JSON NULL;');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive changes
    }
}
