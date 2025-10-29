<?php

declare(strict_types=1);

namespace SquarePayments\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Psr\Log\LoggerInterface;

class TransactionLogger
{
    private SquarePaymentsTransactionService $squarePaymentsTransactionService;

    public function __construct(
        SquarePaymentsTransactionService $squarePaymentsTransactionService
    ) {
        $this->squarePaymentsTransactionService = $squarePaymentsTransactionService;
    }

    /**
     * @param array<string,mixed> $responseData
     */
    public function logTransaction(
        string $type,
        array $responseData,
        string $orderTransactionId,
        Context $context,
        ?string $squareTransactionId = null
    ): void {
        $transactionData = new TransactionData(
            type: strtoupper($type),
            transactionId: $responseData['id'] ?? null,
            paymentId: $this->generatePaymentId(),
            cardCategory: $this->getCardCategory($responseData['source_type'] ?? null),
            paymentMethodType: $responseData['card_details']['card']['card_brand'] ?? null,
            expiryMonth: isset($responseData['card_details']['card']['exp_month'])
                ? (string)$responseData['card_details']['card']['exp_month']
                : null,
            expiryYear:isset($responseData['card_details']['card']['exp_year'])
                ? (string)$responseData['card_details']['card']['exp_year']
                : null,
            cardLast4: isset($responseData['card_details']['card']['last_4']) ?
                (string)$responseData['card_details']['card']['last_4'] : null,
            lastUpdate: $responseData['updated_at'] ?? $responseData['created_at'] ?? null,
            amount: isset($responseData['amount_money']['amount'])
                ? (string)$responseData['amount_money']['amount']
                : null,
            currency: $responseData['amount_money']['currency'] ?? null,
            statusCode: $responseData['status'] ?? null
        );
        if ($squareTransactionId) {
            $this->updateSquareTransactionCustomFields(
                $transactionData->toArray(),
                $squareTransactionId,
                $context
            );
        }
    }

    /**
     * @param array<string,mixed> $newTransaction
     */
    public function updateSquareTransactionCustomFields(
        array $newTransaction,
        string $transactionId,
        Context $context
    ): void {
        if (isset($newTransaction['amount'])) {
            $newTransaction['amount'] = (string)(round((int)$newTransaction['amount']) / 100);
        }
        $criteria = new Criteria([
            $transactionId
        ]);
        $transactionEntity = $this->squarePaymentsTransactionService
            ->getRepository()
            ->search($criteria, $context)
            ->first();
        if ($transactionEntity) {
            $this->squarePaymentsTransactionService->getRepository()->update([
                [
                    'id' => $transactionId,
                    'customFields' => $newTransaction,
                ],
            ], $context);
        }
    }
    private function generatePaymentId(): string
    {
        return bin2hex(random_bytes(15)); // Generates a random string of 30 characters
    }
    public function getCardCategory(?string $scheme): string
    {
        if (!$scheme) {
            return '-';
        }
        return str_contains($scheme, 'card') ? 'CreditCard' : 'DebitCard';
    }
}
