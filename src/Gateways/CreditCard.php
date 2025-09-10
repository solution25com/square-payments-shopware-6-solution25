<?php

namespace SquarePayments\Gateways;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
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

class CreditCard extends AbstractPaymentHandler
{


    public function __construct(
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly SquarePaymentsTransactionService $transactionService,
        private readonly LoggerInterface $logger,
        private readonly SquareConfigService $squareConfigService,
        private readonly EntityRepository $orderTransactionRepository,
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

        $redirectUrl = $this->shouldRedirect($paymentMode);
        if ($redirectUrl) {
            return new RedirectResponse($redirectUrl);
        }

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
            $context
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
        return /*$type === PaymentHandlerType::PAYMENT &&*/ $paymentMethodId === $this->getPaymentMethodId();
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

    private function getPaymentMethodId(): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', 'SquarePayments\Gateways\CreditCard'));
        $paymentMethod = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext())->first();

        return $paymentMethod ? $paymentMethod->getId() : null;
    }

    private function getOrderIdFromTransaction(string $orderTransactionId, Context $context): string
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order');
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($orderTransaction && $orderTransaction->getOrder()) {
            return $orderTransaction->getOrder()->getId();
        }

        throw new \RuntimeException('Order ID not found for transaction ID: ' . $orderTransactionId);
    }

    private function getPaymentMethodName(PaymentTransactionStruct $transaction, Context $context): string
    {
        $criteria = new Criteria([$transaction->getOrderTransactionId()]);
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($orderTransaction && $orderTransaction->getPaymentMethod()) {
            return $orderTransaction->getPaymentMethod()->getName();
        }

        return (new CreditCardPaymentMethod())->getName();
    }

    private function shouldRedirect(string $paymentMode): ?string
    {
        // Example: Redirect to Square payment gateway if not in production mode or specific logic
//        $config = $this->configRepository->search(
//            (new Criteria())->addFilter(new EqualsFilter('name', 'SquarePayments.config.mode')),
//            Context::createDefaultContext()
//        )->first();
//
//        $mode = $config ? $config->get('configuration')['value'] ?? 'sandbox' : 'sandbox';
//        if ($mode === 'sandbox') {
//            // Simulate redirect to Square sandbox (replace with actual API call)
//            return 'https://sandbox.squareup.com/payment/gateway'; // Placeholder URL
//        }

        return null; // No redirect in production for now
    }
}