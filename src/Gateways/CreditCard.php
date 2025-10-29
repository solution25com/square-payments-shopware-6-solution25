<?php

namespace SquarePayments\Gateways;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use SquarePayments\Library\TransactionStatuses;
use SquarePayments\Library\TransactionType;
use SquarePayments\PaymentMethods\CreditCardPaymentMethod;
use SquarePayments\Service\SquareConfigService;
use SquarePayments\Service\SquarePaymentsTransactionService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SquarePayments\Service\TransactionLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;

class CreditCard extends AbstractPaymentHandler
{
    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        /** @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository */
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly SquarePaymentsTransactionService $transactionService,
        private readonly LoggerInterface $logger,
        private readonly SquareConfigService $squareConfigService,
        /** @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository */
        private readonly EntityRepository $orderTransactionRepository,
        /** @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository */
        private readonly EntityRepository $paymentMethodRepository,
        private readonly TransactionLogger $transactionLogger
    ) {
    }

    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct = null
    ): ?RedirectResponse {
        $dataBag = new RequestDataBag($request->request->all());
        $this->logger->debug('pay of credit card');
        $this->logger->debug('Credit Card Payment Request DataBag', [$dataBag]);
        $status = $dataBag->get('squarepayments_payment_status');
        $paymentData = $dataBag->get('square_payment_data');
        $transactionId = $dataBag->get('squarepayments_transaction_id');
        $isSubscription = $dataBag->get('squarepayments_is_subscription') == "1";
        $subscriptionCard = $dataBag->get('squarepayments_subscription_card') ?? "";

        if ($status !== 'success' || !$transactionId) {
            $this->logger->warning('Payment failed: Invalid status or transaction ID', [
                'status' => $status,
                'transactionId' => $transactionId,
            ]);
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            return null;
        }

        $paymentMode = $this->squareConfigService->get('paymentMode');
        $paymentMethodName = $this->getPaymentMethodName($transaction, $context);
        $orderId = $this->getOrderIdFromTransaction($transaction->getOrderTransactionId(), $context);

        switch ($paymentMode) {
            case 'AUTHORIZE_AND_CAPTURE':
                $this->transactionStateHandler->paid($transaction->getOrderTransactionId(), $context);
                $status = TransactionStatuses::PAID->value;
                break;
            default:
                $this->transactionStateHandler->authorize($transaction->getOrderTransactionId(), $context);
                $status = TransactionStatuses::AUTHORIZED->value;
                break;
        }

        $squareTransactionId = $this->transactionService->addTransaction(
            $orderId,
            $paymentMethodName,
            $transactionId,
            $status,
            $context,
            $isSubscription ? json_decode($subscriptionCard, true) : []
        );
        $paymentData = is_array($paymentData) ? $paymentData : json_decode($paymentData, true);
        $this->transactionLogger->logTransaction($status, $paymentData, $orderId, $context, $squareTransactionId);
        return null;
    }

    public function supports(
        PaymentHandlerType $type,
        string $paymentMethodId,
        Context $context
    ): bool {
        return $paymentMethodId === $this->getPaymentMethodId($context);
    }

    public function finalize(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context
    ): void {
        $dataBag = new RequestDataBag($request->request->all());
        $status = $dataBag->get('status');
        $paymentData = $dataBag->get('square_payment_data');
        $squarePaymentsTransactionId = $dataBag->get('squarepayments_transaction_id');
        if ($status === 'success' && $squarePaymentsTransactionId) {
            $paymentMode = $this->squareConfigService->get('paymentMode');
            $orderId = $this->getOrderIdFromTransaction($transaction->getOrderTransactionId(), $context);
            $paymentMethodName = $this->getPaymentMethodName($transaction, $context);

            switch ($paymentMode) {
                case TransactionType::STORE->value:
                    $this->transactionStateHandler->authorize($transaction->getOrderTransactionId(), $context);
                    $squareTransactionId = $this->transactionService->addTransaction(
                        $orderId,
                        $paymentMethodName,
                        $squarePaymentsTransactionId,
                        TransactionStatuses::AUTHORIZED->value,
                        $context
                    );
                    break;
                default:
                    $this->transactionStateHandler->paid($transaction->getOrderTransactionId(), $context);
                    $squareTransactionId = $this->transactionService->addTransaction(
                        $orderId,
                        $paymentMethodName,
                        $squarePaymentsTransactionId,
                        TransactionStatuses::PAID->value,
                        $context
                    );
                    break;
            }
            $paymentData = is_array($paymentData) ? $paymentData : json_decode($paymentData, true);
            $this->transactionLogger->logTransaction($status, $paymentData, $orderId, $context, $squareTransactionId);
        } else {
            $this->logger->warning('Finalize failed: Invalid status or transaction ID', [
                'status' => $status,
                'transactionId' => $squarePaymentsTransactionId,
            ]);
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
        }
    }

    private function getPaymentMethodId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', 'SquarePayments\Gateways\CreditCard'));
        $paymentMethod = $this->paymentMethodRepository->search($criteria, $context)->first();

        return $paymentMethod instanceof PaymentMethodEntity ? $paymentMethod->getId() : null;
    }

    private function getOrderIdFromTransaction(string $orderTransactionId, Context $context): string
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order');
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($orderTransaction instanceof OrderTransactionEntity && $orderTransaction->getOrder()) {
            return $orderTransaction->getOrder()->getId();
        }

        throw new \RuntimeException('Order ID not found for transaction ID: ' . $orderTransactionId);
    }

    private function getPaymentMethodName(PaymentTransactionStruct $transaction, Context $context): string
    {
        $criteria = new Criteria([$transaction->getOrderTransactionId()]);
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($orderTransaction instanceof OrderTransactionEntity && $orderTransaction->getPaymentMethod()) {
            return (string)($orderTransaction->getPaymentMethod()->getName() ?? '');
        }

        return (new CreditCardPaymentMethod())->getName();
    }
}
