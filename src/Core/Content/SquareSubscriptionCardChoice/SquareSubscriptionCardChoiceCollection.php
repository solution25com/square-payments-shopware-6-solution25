<?php

declare(strict_types=1);

namespace SquarePayments\Core\Content\SquareSubscriptionCardChoice;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SquareSubscriptionCardChoiceEntity>
 */
class SquareSubscriptionCardChoiceCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SquareSubscriptionCardChoiceEntity::class;
    }
}
