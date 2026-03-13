<?php

declare(strict_types=1);

namespace SquarePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;


class Migration1773000000BackfillSquareSubscriptionCardChoice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773000000;
    }

    public function update(Connection $connection): void
    {

        $subscriptionsToBackfill = $connection->fetchAllAssociative(<<<SQL
            SELECT DISTINCT
                s.id AS subscription_id,
                s_customer.customer_id,
                ot.id AS order_transaction_id
            FROM s25_subscription s
            INNER JOIN s25_subscription_customer s_customer ON s.id = s_customer.subscription_id
            INNER JOIN `order` o ON JSON_EXTRACT(s.converted_order, '$.id') = HEX(o.id)
            INNER JOIN order_transaction ot ON o.id = ot.order_id
            LEFT JOIN square_subscription_card_choice scc ON s.id = UNHEX(REPLACE(scc.subscription_id, '-', ''))
                AND s_customer.customer_id = scc.customer_id
            WHERE scc.id IS NULL
                AND ot.custom_fields IS NOT NULL
                AND JSON_EXTRACT(ot.custom_fields, '$.recurringPayment.reference') IS NOT NULL
                AND JSON_EXTRACT(ot.custom_fields, '$.recurringPayment.meta.provider') = 'square'
                AND JSON_EXTRACT(ot.custom_fields, '$.recurringPayment.meta.squareCustomerId') IS NOT NULL
            LIMIT 1000
        SQL);

        if (empty($subscriptionsToBackfill)) {
            return;
        }

        $inserts = [];
        foreach ($subscriptionsToBackfill as $subscription) {
            $subscriptionId = $subscription['subscription_id'];
            $customerId = $subscription['customer_id'];
            $orderTransactionId = $subscription['order_transaction_id'];

            $customFieldsJson = $connection->fetchOne(<<<SQL
                SELECT custom_fields
                FROM order_transaction
                WHERE id = UNHEX(?)
            SQL, [$orderTransactionId]);

            if ($customFieldsJson === false || $customFieldsJson === null) {
                continue;
            }

            $customFields = json_decode((string) $customFieldsJson, true);
            if (!\is_array($customFields)) {
                continue;
            }

            $recurringPayment = $customFields['recurringPayment'] ?? null;
            if (!\is_array($recurringPayment)) {
                continue;
            }

            $cardId = $recurringPayment['reference'] ?? null;
            if (!\is_string($cardId) || $cardId === '') {
                continue;
            }

            $meta = $recurringPayment['meta'] ?? [];
            $provider = $meta['provider'] ?? null;
            if ($provider !== 'square') {
                continue;
            }

            $subscriptionIdHex = str_replace('-', '', $subscriptionId);
            if (strlen($subscriptionIdHex) !== 32) {
                try {
                    $subscriptionIdHex = str_replace('-', '', Uuid::fromBytesToHex($subscriptionId));
                } catch (\Exception $e) {
                    continue;
                }
            }

            $inserts[] = [
                'id' => Uuid::randomBytes(),
                'customer_id' => hex2bin(str_replace('-', '', $customerId)),
                'subscription_id' => $subscriptionIdHex,
                'square_card_id' => $cardId,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
            ];
        }

        if (!empty($inserts)) {
            $values = [];
            foreach ($inserts as $insert) {
                $values[] = sprintf(
                    "(UNHEX('%s'), UNHEX('%s'), '%s', '%s', '%s')",
                    bin2hex($insert['id']),
                    bin2hex($insert['customer_id']),
                    $connection->quote($insert['subscription_id']),
                    $connection->quote($insert['square_card_id']),
                    $insert['created_at']
                );
            }
            
            $connection->executeStatement(<<<SQL
                INSERT IGNORE INTO square_subscription_card_choice
                    (id, customer_id, subscription_id, square_card_id, created_at)
                VALUES
            SQL . implode(',', $values));
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive changes
    }
}
