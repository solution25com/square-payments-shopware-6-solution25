<?php

declare(strict_types=1);

namespace SquarePayments\Service;

class TransactionData
{
    private string $type;
    private ?string $transactionId;
    private ?string $paymentId;
    private ?string $cardCategory;
    private ?string $paymentMethodType;
    private ?string $expiryMonth;
    private ?string $expiryYear;
    private ?string $cardLast4;
    private string $lastUpdate;
    private ?string $amount;
    private ?string $currency;
    private ?string $statusCode;

    public function __construct(
        string $type,
        ?string $transactionId,
        ?string $paymentId,
        ?string $cardCategory,
        ?string $paymentMethodType,
        ?string $expiryMonth,
        ?string $expiryYear,
        ?string $cardLast4,
        string $lastUpdate,
        ?string $amount = null,
        ?string $currency = null,
        ?string $statusCode = null
    ) {
        $this->type = $type;
        $this->transactionId = $transactionId;
        $this->paymentId = $paymentId;
        $this->cardCategory = $cardCategory;
        $this->paymentMethodType = $paymentMethodType;
        $this->expiryMonth = $expiryMonth;
        $this->expiryYear = $expiryYear;
        $this->cardLast4 = $cardLast4;
        $this->lastUpdate = $lastUpdate;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->statusCode = $statusCode;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'transaction_id' => $this->transactionId,
            'payment_id' => $this->paymentId,
            'card_category' => $this->cardCategory,
            'payment_method_type' => $this->paymentMethodType,
            'expiry_month' => $this->expiryMonth,
            'expiry_year' => $this->expiryYear,
            'card_last_4' => $this->cardLast4,
            'last_update' => $this->lastUpdate,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status_code' => $this->statusCode,
        ];
    }
}
