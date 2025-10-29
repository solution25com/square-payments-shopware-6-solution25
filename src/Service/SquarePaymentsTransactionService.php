<?php

namespace SquarePayments\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use SquarePayments\Core\Content\Transaction\SquarePaymentsTransactionCollection;
use Shopware\Core\Checkout\Order\OrderCollection;

class SquarePaymentsTransactionService
{
    /**
     * @param EntityRepository<SquarePaymentsTransactionCollection> $squarePaymentsTransactionRepository
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $squarePaymentsTransactionRepository,
        private readonly EntityRepository $orderRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string,mixed> $subscriptionCard
     */
    public function addTransaction(string $orderId, string $paymentMethodName, string $transactionId, string $status, Context $context, array $subscriptionCard = []): string
    {
        $tableSquarePaymentsId = Uuid::randomHex();
        $this->logger->debug('Adding transaction for order id ' . $orderId . ' to ' . $tableSquarePaymentsId);

        $this->squarePaymentsTransactionRepository->upsert([
            [
                'id' => $tableSquarePaymentsId,
                'orderId' => $orderId,
                'paymentMethodName' => $paymentMethodName,
                'transactionId' => $transactionId,
                'subscriptionCard' => $subscriptionCard,
                'isSubscription' => !empty($subscriptionCard),
                'status' => $status,
                'createdAt' => (new \DateTime())->format('Y-m-d H:i:s')
            ]
        ], $context);

        $this->orderRepository->upsert([
            [
                'id' => $orderId,
                'squarePaymentsTransaction' => [
                    'data' => [
                        'id' => $tableSquarePaymentsId,
                        'squarePaymentsTransactionId' => $transactionId,
                        'paymentMethodName' => $paymentMethodName,
                        'status' => $status,
                    ]
                ]
            ]
        ], $context);
        return $tableSquarePaymentsId;
    }

    public function getTransactionByOrderId(string $orderId, Context $context): null|Entity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        try {
            return $this->squarePaymentsTransactionRepository->search($criteria, $context)->first();
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());
            return null;
        }
    }

    /**
     * @return EntityRepository<SquarePaymentsTransactionCollection>
     */
    public function getRepository(): EntityRepository
    {
        return $this->squarePaymentsTransactionRepository;
    }
}
