<?php

declare(strict_types=1);

namespace SquarePayments\Core\Content\SquareSubscriptionCardChoice;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class SquareSubscriptionCardChoiceEntity extends Entity
{
    use EntityIdTrait;

    protected string $customerId;

    protected string $subscriptionId;

    protected string $squareCardId;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getSubscriptionId(): string
    {
        return $this->subscriptionId;
    }

    public function setSubscriptionId(string $subscriptionId): void
    {
        $this->subscriptionId = $subscriptionId;
    }

    public function getSquareCardId(): string
    {
        return $this->squareCardId;
    }

    public function setSquareCardId(string $squareCardId): void
    {
        $this->squareCardId = $squareCardId;
    }
}
