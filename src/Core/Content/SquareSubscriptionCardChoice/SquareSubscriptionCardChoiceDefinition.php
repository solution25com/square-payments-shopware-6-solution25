<?php

declare(strict_types=1);

namespace SquarePayments\Core\Content\SquareSubscriptionCardChoice;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class SquareSubscriptionCardChoiceDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'square_subscription_card_choice';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SquareSubscriptionCardChoiceEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SquareSubscriptionCardChoiceCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new StringField('subscription_id', 'subscriptionId'))->addFlags(new Required(), new ApiAware()),
            (new StringField('square_card_id', 'squareCardId'))->addFlags(new Required(), new ApiAware()),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
