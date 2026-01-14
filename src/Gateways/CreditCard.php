<?php

namespace SquarePayments\Gateways;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use SquarePayments\Core\Content\SquareSubscriptionCardChoice\SquareSubscriptionCardChoiceEntity;
use SquarePayments\Library\TransactionStatuses;
use SquarePayments\Library\TransactionType;
use SquarePayments\PaymentMethods\CreditCardPaymentMethod;
use SquarePayments\Service\SquareConfigService;
use SquarePayments\Service\SquarePaymentService;
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
use Shopware\Core\Framework\Uuid\Uuid;
use SquarePayments\Storefront\Service\SquareSubscriptionIdExtractor;

class CreditCard extends AbstractPaymentHandler
{
    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly SquarePaymentsTransactionService $transactionService,
        private readonly LoggerInterface $logger,
        private readonly SquareConfigService $squareConfigService,
        private readonly EntityRepository $orderTransactionRepository,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly TransactionLogger $transactionLogger,
        private readonly SquarePaymentService $squarePaymentService,
        private readonly \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository $squareSubscriptionCardChoiceRepository,
        private readonly SquareSubscriptionIdExtractor $subscriptionIdExtractor,
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

        if ($isSubscription && $subscriptionCard !== '') {
            $decodedSubscriptionCard = json_decode((string) $subscriptionCard, true);
            if (\is_array($decodedSubscriptionCard)) {
                $existingCustomFields = $this->getOrderTransactionCustomFields($transaction->getOrderTransactionId(), $context);

                $existingCustomFields['recurringPayment'] = [
                    'reference' => $decodedSubscriptionCard['id'] ?? null,
                    'meta' => [
                        'provider' => 'square',
                        'cardId' => $decodedSubscriptionCard['id'] ?? null,
                        'squareCustomerId' => $decodedSubscriptionCard['customer_id'] ?? null,
                        'subscriptionCard' => $decodedSubscriptionCard,
                    ],
                ];

                $this->orderTransactionRepository->upsert([
                    [
                        'id' => $transaction->getOrderTransactionId(),
                        'customFields' => $existingCustomFields,
                    ]
                ], $context);
            }
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
            $context,
            $isSubscription ? json_decode($subscriptionCard, true) : []
        );
        $paymentDataRaw = $paymentData;
        $paymentData = is_array($paymentDataRaw) ? $paymentDataRaw : (json_decode((string)$paymentDataRaw, true) ?: []);
        $this->transactionLogger->logTransaction($status, $paymentData, $orderId, $context, $squareTransactionId);
        return null;
    }

    public function supports(
        PaymentHandlerType $type,
        string $paymentMethodId,
        Context $context
    ): bool {
        if ($type === PaymentHandlerType::RECURRING) {
            return true;
        }
        return false;
    }

    public function recurring(
        PaymentTransactionStruct $transaction,
        Context $context
    ): void {
        $orderTransactionId = $transaction->getOrderTransactionId();

        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('order.price');
        $criteria->addAssociation('paymentMethod');

        /** @var OrderTransactionEntity|null $orderTransaction */
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if (!$orderTransaction instanceof OrderTransactionEntity) {
            throw PaymentException::recurringInterrupted($orderTransactionId, 'Order transaction not found');
        }

        $order = $orderTransaction->getOrder();
        if (!$order instanceof OrderEntity) {
            throw PaymentException::recurringInterrupted($orderTransactionId, 'Order not found for transaction');
        }

        $customerId = $order->getOrderCustomer()?->getCustomerId();
        $subscriptionId = $this->subscriptionIdExtractor->extract($order);

        if (\is_string($customerId) && $customerId !== '' && \is_string($subscriptionId) && Uuid::isValid($subscriptionId)) {
            $choiceCriteria = new Criteria();
            $choiceCriteria->addFilter(new EqualsFilter('customerId', $customerId));
            $choiceCriteria->addFilter(new EqualsFilter('subscriptionId', $subscriptionId));

            /** @var SquareSubscriptionCardChoiceEntity $choice */
            $choice = $this->squareSubscriptionCardChoiceRepository->search($choiceCriteria, $context)->first();

            /* @phpstan-ignore-next-line */
            if ($choice && \is_string($choice->getSquareCardId()) && $choice->getSquareCardId() !== '') {
                $existingCustomFields = $orderTransaction->getCustomFields() ?? [];
                $recurringPayment = \is_array($existingCustomFields['recurringPayment'] ?? null) ? (array) $existingCustomFields['recurringPayment'] : [];
                $meta = \is_array($recurringPayment['meta'] ?? null) ? (array) $recurringPayment['meta'] : [];

                $recurringPayment['reference'] = $choice->getSquareCardId();
                $meta['provider'] = 'square';
                $meta['cardId'] = $choice->getSquareCardId();
                $recurringPayment['meta'] = $meta;

                $existingCustomFields['recurringPayment'] = $recurringPayment;

                $this->orderTransactionRepository->upsert([
                    [
                        'id' => $orderTransactionId,
                        'customFields' => $existingCustomFields,
                    ]
                ], $context);

                $orderTransaction->setCustomFields($existingCustomFields);
            }
        }

        $customFields = $orderTransaction->getCustomFields() ?? [];
        $recurringPayment = \is_array($customFields['recurringPayment'] ?? null) ? (array) $customFields['recurringPayment'] : [];

        $cardId = \is_string($recurringPayment['reference'] ?? null) ? (string) $recurringPayment['reference'] : null;
        $meta = \is_array($recurringPayment['meta'] ?? null) ? (array) $recurringPayment['meta'] : [];
        $squareCustomerId = \is_string($meta['squareCustomerId'] ?? null) ? (string) $meta['squareCustomerId'] : null;

        if (!$cardId) {
            throw PaymentException::recurringInterrupted($orderTransactionId, 'Missing Square recurring card reference (customFields.recurringPayment.reference)');
        }

        if (!$squareCustomerId) {
            throw PaymentException::recurringInterrupted($orderTransactionId, 'Missing Square customer id for recurring charge (customFields.recurringPayment.meta.squareCustomerId)');
        }

        $currencyIsoCode = (string) ($order->getCurrency()?->getIsoCode() ?? '');
        if ($currencyIsoCode === '') {
            throw PaymentException::recurringInterrupted($orderTransactionId, 'Missing currency on order for recurring payment');
        }

        $totalPrice = (float) ($order->getPrice()->getTotalPrice());

        $amountMinor = (int) \round($totalPrice * 100);
        if (\in_array(strtoupper($currencyIsoCode), ['JPY', 'KRW', 'VND', 'CLP', 'UGX', 'XAF', 'XOF', 'KMF'], true)) {
            $amountMinor = (int) \round($totalPrice);
        }

        $result = $this->squarePaymentService->processRecurringPayment(
            cardId: $cardId,
            orderId: $order->getId(),
            squareCustomerId: $squareCustomerId,
            amountMinor: $amountMinor,
            currencyIsoCode: $currencyIsoCode,
            orderTransactionId: $orderTransactionId,
            context: $context
        );

        if (($result['status'] ?? null) !== 'success') {
            $message = (string) ($result['message'] ?? 'unknown error');
            throw PaymentException::recurringInterrupted($orderTransactionId, 'Square recurring payment failed: ' . $message);
        }

        $this->transactionStateHandler->paid($orderTransactionId, $context);
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

    /* @phpstan-ignore-next-line */
    private function getPaymentMethodId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', CreditCard::class));
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
        $criteria->addAssociation('paymentMethod');
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($orderTransaction instanceof OrderTransactionEntity && $orderTransaction->getPaymentMethod()) {
            return (string)($orderTransaction->getPaymentMethod()->getName() ?? '');
        }

        return (new CreditCardPaymentMethod())->getName();
    }

    /** @return array<string,mixed> */
    private function getOrderTransactionCustomFields(string $orderTransactionId, Context $context): array
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('customFields');

        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($orderTransaction instanceof OrderTransactionEntity) {
            $fields = $orderTransaction->getCustomFields();
            return \is_array($fields) ? $fields : [];
        }

        return [];
    }
}
