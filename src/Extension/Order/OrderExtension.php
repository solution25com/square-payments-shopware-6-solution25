<?php

namespace SquarePayments\Extension\Order;

use SquarePayments\Core\Content\Transaction\SquarePaymentsTransactionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('squarePaymentsTransaction', SquarePaymentsTransactionDefinition::class, 'order_id'))->addFlags(new ApiAware()),
        );
    }

    public function getDefinitionClass(): string
    {
        return \Shopware\Core\Checkout\Order\OrderDefinition::class;
    }
}

