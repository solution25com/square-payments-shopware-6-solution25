<?php

declare(strict_types=1);

namespace SquarePayments\Core\Content\Transaction;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class SquarePaymentsTransactionEntity extends Entity
{
    use EntityIdTrait;

    /** @var string */
    protected $id;
    protected ?string $orderId;
    protected string $paymentMethodName;
    protected string $transactionId;
    protected string $status;
    protected bool $isSubscription;
    /** @var array<string,mixed>|null */
    protected ?array $subscriptionCard = null;
    /** @var array<string,mixed> */
    protected array $customFields = [];

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getOrderId(): ?string
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

    /** @return array<string,mixed>|null */
    public function getSubscriptionCard(): ?array
    {
        return $this->subscriptionCard;
    }

    /** @param array<string,mixed>|null $subscriptionCard */
    public function setSubscriptionCard(?array $subscriptionCard): void
    {
        $this->subscriptionCard = $subscriptionCard;
    }

    /** @return array<string,mixed> */
    public function getCustomFields(): array
    {
        return $this->customFields;
    }

    /** @param array<string,mixed> $customFields */
    public function setCustomFields(array $customFields): void
    {
        $this->customFields = $customFields;
    }
}
