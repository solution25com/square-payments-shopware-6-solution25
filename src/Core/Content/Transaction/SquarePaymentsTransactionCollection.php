<?php

declare(strict_types=1);

namespace SquarePayments\Core\Content\Transaction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(SquarePaymentsTransactionEntity $entity)
 * @method void set(string $key, SquarePaymentsTransactionEntity $entity)
 * @method SquarePaymentsTransactionEntity[] getIterator()
 * @method SquarePaymentsTransactionEntity[] getElements()
 * @method SquarePaymentsTransactionEntity|null get(string $key)
 * @method SquarePaymentsTransactionEntity|null first()
 * @method SquarePaymentsTransactionEntity|null last()
 */
class SquarePaymentsTransactionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SquarePaymentsTransactionEntity::class;
    }
}

