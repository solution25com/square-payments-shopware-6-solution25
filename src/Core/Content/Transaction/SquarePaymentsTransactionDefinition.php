<?php

declare(strict_types=1);

namespace SquarePayments\Core\Content\Transaction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;

class SquarePaymentsTransactionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'squarepayments_transaction';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SquarePaymentsTransactionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SquarePaymentsTransactionCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new Required(), new PrimaryKey()),
            (new StringField('order_id', 'orderId'))->addFlags(new ApiAware()),
            (new StringField('payment_method_name', 'paymentMethodName'))->addFlags(new ApiAware()),
            (new StringField('transaction_id', 'transactionId'))->addFlags(new ApiAware(), new Required()),
            (new BoolField('is_subscription', 'isSubscription'))->addFlags(new ApiAware()),
            (new JsonField('subscription_card', 'subscriptionCard'))->addFlags(new ApiAware()),
            (new StringField('status', 'status'))->addFlags(new ApiAware(), new Required()),
            new CustomFields(),
        ]);
    }
}
