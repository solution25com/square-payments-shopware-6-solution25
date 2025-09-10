<?php

namespace SquarePayments\Gateways;

use SquarePayments\Library\TransactionStatuses;
use SquarePayments\Service\SquarePaymentsTransactionService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class GooglePay implements SynchronousPaymentHandlerInterface
{
    private OrderTransactionStateHandler $transactionStateHandler;
    private SquarePaymentsTransactionService $squarePaymentsTransactionService;
    private LoggerInterface $logger;

    public function __construct(
        OrderTransactionStateHandler     $transactionStateHandler,
        SquarePaymentsTransactionService $squarePaymentsTransactionService,
        LoggerInterface                  $logger
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->squarePaymentsTransactionService = $squarePaymentsTransactionService;
        $this->logger = $logger;
    }

    public function pay(
        SyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): void
    {
        $context = $salesChannelContext->getContext();
        $squarePaymentsTransactionId = $dataBag->get('squarepayments_transaction_id');
        $paymentMethodName = $salesChannelContext->getPaymentMethod()->getName();
        $orderId = $transaction->getOrder()->getId();
        $this->transactionStateHandler->paid(
            $transaction->getOrderTransaction()->getId(),
            $context
        );
        $squareTransactionId = $this->squarePaymentsTransactionService->addTransaction(
            $orderId,
            $paymentMethodName,
            $squarePaymentsTransactionId,
            TransactionStatuses::PAID->value,
            $context);
    }
}
