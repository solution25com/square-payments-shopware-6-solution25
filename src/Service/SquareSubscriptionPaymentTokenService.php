<?php

declare(strict_types=1);

namespace SquarePayments\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use SquarePayments\Core\Content\SquareSubscriptionCardChoice\SquareSubscriptionCardChoiceEntity;
use SquarePayments\Storefront\Service\SquareSubscriptionIdExtractor;

class SquareSubscriptionPaymentTokenService
{
    public function __construct(
        private readonly EntityRepository $squareSubscriptionCardChoiceRepository,
        private readonly EntityRepository $orderTransactionRepository,
        private readonly SquareSubscriptionIdExtractor $subscriptionIdExtractor,
        private readonly SquareCardService $squareCardService,
    ) {
    }


    public function resolvePaymentToken(
        string $subscriptionId,
        string $customerId,
        ?OrderTransactionEntity $orderTransaction,
        Context $context
    ): ?array {
        $currentOrderToken = $orderTransaction !== null ? $this->getTokenFromOrderTransaction($orderTransaction) : null;
        $choice = $this->getCardChoice($subscriptionId, $customerId, $context);

        if ($choice !== null) {
            $cardId = $choice->getSquareCardId();
            if ($cardId !== '') {
                $squareCustomerId = $currentOrderToken['squareCustomerId'] ?? null;
                $squareCustomerId ??= $this->squareCardService->getSquareCustomerId($customerId, $context);

                if ($squareCustomerId !== null) {
                    return [
                        'cardId' => $cardId,
                        'squareCustomerId' => $squareCustomerId,
                    ];
                }
            }
        }

        if ($currentOrderToken !== null && $orderTransaction !== null) {
            $this->createCardChoiceFromOrderTransaction(
                $subscriptionId,
                $customerId,
                $orderTransaction,
                $context
            );

            return $currentOrderToken;
        }

        return null;
    }

    public function getCardChoice(string $subscriptionId, string $customerId, Context $context): ?SquareSubscriptionCardChoiceEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('subscriptionId', $subscriptionId));
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));

        /** @var SquareSubscriptionCardChoiceEntity|null */
        return $this->squareSubscriptionCardChoiceRepository->search($criteria, $context)->first();
    }

    public function setCardChoice(
        string $subscriptionId,
        string $customerId,
        string $cardId,
        Context $context
    ): void {
        $existing = $this->getCardChoice($subscriptionId, $customerId, $context);

        $payload = [
            'id' => $existing?->getId() ?? Uuid::randomHex(),
            'subscriptionId' => $subscriptionId,
            'customerId' => $customerId,
            'squareCardId' => $cardId,
        ];

        $this->squareSubscriptionCardChoiceRepository->upsert([$payload], $context);
    }

    public function createCardChoiceFromOrderTransaction(
        string $subscriptionId,
        string $customerId,
        OrderTransactionEntity $orderTransaction,
        Context $context
    ): void {
        $token = $this->getTokenFromOrderTransaction($orderTransaction);
        if ($token === null) {
            return;
        }

        $this->setCardChoice($subscriptionId, $customerId, $token['cardId'], $context);
    }

    private function getTokenFromOrderTransaction(OrderTransactionEntity $orderTransaction): ?array
    {
        $customFields = $orderTransaction->getCustomFields() ?? [];
        $recurringPayment = \is_array($customFields['recurringPayment'] ?? null) 
            ? (array) $customFields['recurringPayment'] 
            : [];
        
        $cardId = \is_string($recurringPayment['reference'] ?? null) 
            ? (string) $recurringPayment['reference'] 
            : null;
        
        if ($cardId === null || $cardId === '') {
            return null;
        }

        $meta = \is_array($recurringPayment['meta'] ?? null) 
            ? (array) $recurringPayment['meta'] 
            : [];
        
        $squareCustomerId = \is_string($meta['squareCustomerId'] ?? null) 
            ? (string) $meta['squareCustomerId'] 
            : null;

        if ($squareCustomerId === null || $squareCustomerId === '') {
            return null;
        }

        return [
            'cardId' => $cardId,
            'squareCustomerId' => $squareCustomerId,
        ];
    }

}
