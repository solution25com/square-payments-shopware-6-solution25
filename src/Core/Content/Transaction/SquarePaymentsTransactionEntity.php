<?php

declare(strict_types=1);

namespace SquarePayments\Core\Content\Transaction;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class SquarePaymentsTransactionEntity extends Entity
{
    use EntityIdTrait;

    protected $id;
    protected string $orderId;
    protected string $paymentMethodName;
    protected string $transactionId;
    protected string $status;
    protected bool $isSubscription;
    protected string $subscriptionTransactionId;
    protected array $customFields = [];

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getPaymentMethodName(): string
    {
        return $this->paymentMethodName;
    }

    public function setPaymentMethodName(string $paymentMethodName): void
    {
        $this->paymentMethodName = $paymentMethodName;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function setTransactionId(string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }
    public function getIsSubscription(): bool
    {
        return $this->isSubscription;
    }

    public function setIsSubscription(bool $isSubscription): void
    {
        $this->isSubscription = $isSubscription;
    }

    public function getSubscriptionTransactionId(): string
    {
        return $this->subscriptionTransactionId;
    }

    public function setSubscriptionTransactionId(string $subscriptionTransactionId): void
    {
        $this->subscriptionTransactionId = $subscriptionTransactionId;
    }

    public function getCustomFields(): array
    {
        return $this->customFields;
    }

    public function setCustomFields(array $customFields): void
    {
        $this->customFields = $customFields;
    }
}
