<?php

namespace SquarePayments\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class SquarePaymentsTransactionService
{

    public function __construct(
        private readonly EntityRepository $squarePaymentsTransactionRepository,
        private readonly EntityRepository $orderRepository,
        private readonly LoggerInterface $logger
    ) {}

    public function addTransaction($orderId, $paymentMethodName, $transactionId, $status, $context): string
    {
        $tableSquarePaymentsId = Uuid::randomHex();
        $this->logger->debug('Adding transaction for order id ' . $orderId . ' to ' . $tableSquarePaymentsId);

        $this->squarePaymentsTransactionRepository->upsert([
            [
                'id' => $tableSquarePaymentsId,
                'orderId' => $orderId,
                'paymentMethodName' => $paymentMethodName,
                'transactionId' => $transactionId,
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

    public function getRepository(): EntityRepository
    {
        return $this->squarePaymentsTransactionRepository;
    }
}
