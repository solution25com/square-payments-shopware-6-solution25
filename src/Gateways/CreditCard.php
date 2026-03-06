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
use SquarePayments\Service\SquareCardService;
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
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;

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
        private readonly EntityRepository $squareSubscriptionCardChoiceRepository,
        private readonly SquareSubscriptionIdExtractor $subscriptionIdExtractor,
        private readonly SquareCardService $squareCardService,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
    ) {
    }

    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct = null
    ): ?RedirectResponse {
        /* @phpstan-ignore-next-line */
        $salesChannelId = (string) ($context->getSource()->getSalesChannelId() ?? '');

        if (!$this->squareConfigService->isConfigured($salesChannelId !== '' ? $salesChannelId : null)) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);

            throw PaymentException::syncProcessInterrupted(
                $transaction->getOrderTransactionId(),
                'Square payment method is not configured for this sales channel.'
            );
        }

        $dataBag = new RequestDataBag($request->request->all());
        $transactionId = $dataBag->get('squarepayments_transaction_id');

        if (!\is_string($transactionId) || $transactionId === '') {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);

            throw PaymentException::syncProcessInterrupted(
                $transaction->getOrderTransactionId(),
                'Square payment id is missing. Please try again or select a different payment method.'
            );
        }

        $paymentMode = (string) ($this->squareConfigService->get('paymentMode') ?? 'AUTHORIZE_AND_CAPTURE');
        $expectedPayment = $this->getExpectedPaymentContext($transaction->getOrderTransactionId(), $context);
        $verification = $this->squarePaymentService->verifyPaymentForExpectedAmount(
            $transactionId,
            $expectedPayment['amountMinor'],
            $expectedPayment['currency'],
            $paymentMode === 'AUTHORIZE_AND_CAPTURE'
        );

        if (($verification['status'] ?? 'error') !== 'success') {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);

            throw PaymentException::syncProcessInterrupted(
                $transaction->getOrderTransactionId(),
                (string) ($verification['message'] ?? 'Square payment verification failed.')
            );
        }

        $paymentData = $dataBag->get('square_payment_data');
        $isSubscription = $dataBag->get('squarepayments_is_subscription') == "1";
        $subscriptionCard = $dataBag->get('squarepayments_subscription_card') ?? "";

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
                $transactionStatus = TransactionStatuses::PAID->value;
                break;
            default:
                $this->transactionStateHandler->authorize($transaction->getOrderTransactionId(), $context);
                $transactionStatus = TransactionStatuses::AUTHORIZED->value;
                break;
        }

        $squareTransactionId = $this->transactionService->addTransaction(
            $this->getOrderIdFromTransaction($transaction->getOrderTransactionId(), $context),
            $this->getPaymentMethodName($transaction, $context),
            $transactionId,
            $transactionStatus,
            $context,
            $isSubscription ? json_decode($subscriptionCard, true) : []
        );
        $paymentDataRaw = $verification['payment'] ?? $paymentData;
        $paymentData = is_array($paymentDataRaw) ? $paymentDataRaw : (json_decode((string)$paymentDataRaw, true) ?: []);
        $this->transactionLogger->logTransaction($transactionStatus, $paymentData, $this->getOrderIdFromTransaction($transaction->getOrderTransactionId(), $context), $context, $squareTransactionId);
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

            /** @var SquareSubscriptionCardChoiceEntity|null $choice */
            $choice = $this->squareSubscriptionCardChoiceRepository->search($choiceCriteria, $context)->first();

            if ($choice && \is_string($choice->getSquareCardId()) && $choice->getSquareCardId() !== '') {
                $existingCustomFields = $orderTransaction->getCustomFields() ?? [];
                $recurringPayment = \is_array($existingCustomFields['recurringPayment'] ?? null) ? (array) $existingCustomFields['recurringPayment'] : [];
                $meta = \is_array($recurringPayment['meta'] ?? null) ? (array) $recurringPayment['meta'] : [];

                $recurringPayment['reference'] = $choice->getSquareCardId();
                $meta['provider'] = 'square';
                $meta['cardId'] = $choice->getSquareCardId();

                if (!\is_string($meta['squareCustomerId'] ?? null) || (string) $meta['squareCustomerId'] === '') {
                    try {
                        $scContext = $this->salesChannelContextFactory->create(
                            Uuid::randomHex(),
                            (string) $order->getSalesChannelId()
                        );
                        $cardsPayload = $this->squareCardService->getSavedCards($scContext);
                    } catch (\Throwable $e) {
                        $cardsPayload = null;
                    }

                    if (\is_array($cardsPayload)) {
                        $cards = $cardsPayload['cards'] ?? [];
                        if (\is_array($cards)) {
                            foreach ($cards as $card) {
                                if (!\is_array($card)) {
                                    continue;
                                }
                                if (($card['id'] ?? null) !== $choice->getSquareCardId()) {
                                    continue;
                                }
                                $cid = $card['customer_id'] ?? $card['customerId'] ?? null;
                                if (\is_string($cid) && $cid !== '') {
                                    $meta['squareCustomerId'] = $cid;
                                }
                                $meta['subscriptionCard'] = $card;
                                break;
                            }
                        }
                    }
                }

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
        $squarePaymentsTransactionId = $dataBag->get('squarepayments_transaction_id');
        if (\is_string($squarePaymentsTransactionId) && $squarePaymentsTransactionId !== '') {
            $paymentMode = $this->squareConfigService->get('paymentMode');
            $expectedPayment = $this->getExpectedPaymentContext($transaction->getOrderTransactionId(), $context);
            $verification = $this->squarePaymentService->verifyPaymentForExpectedAmount(
                $squarePaymentsTransactionId,
                $expectedPayment['amountMinor'],
                $expectedPayment['currency'],
                $paymentMode === 'AUTHORIZE_AND_CAPTURE'
            );

            if (($verification['status'] ?? 'error') !== 'success') {
                $this->logger->warning('Finalize failed: Square verification failed', [
                    'orderTransactionId' => $transaction->getOrderTransactionId(),
                    'transactionId' => $squarePaymentsTransactionId,
                    'reason' => $verification['message'] ?? 'unknown',
                ]);
                $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
                return;
            }

            $orderId = $this->getOrderIdFromTransaction($transaction->getOrderTransactionId(), $context);
            $paymentMethodName = $this->getPaymentMethodName($transaction, $context);

            switch ($paymentMode) {
                case TransactionType::STORE->value:
                    $this->transactionStateHandler->authorize($transaction->getOrderTransactionId(), $context);
                    $transactionStatus = TransactionStatuses::AUTHORIZED->value;
                    $squareTransactionId = $this->transactionService->addTransaction(
                        $orderId,
                        $paymentMethodName,
                        $squarePaymentsTransactionId,
                        $transactionStatus,
                        $context
                    );
                    break;
                default:
                    $this->transactionStateHandler->paid($transaction->getOrderTransactionId(), $context);
                    $transactionStatus = TransactionStatuses::PAID->value;
                    $squareTransactionId = $this->transactionService->addTransaction(
                        $orderId,
                        $paymentMethodName,
                        $squarePaymentsTransactionId,
                        $transactionStatus,
                        $context
                    );
                    break;
            }
            $paymentData = $verification['payment'] ?? [];
            $this->transactionLogger->logTransaction($transactionStatus, is_array($paymentData) ? $paymentData : [], $orderId, $context, $squareTransactionId);
        } else {
            $this->logger->warning('Finalize failed: Invalid status or transaction ID', [
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

    /**
     * @return array{amountMinor:int,currency:string}
     */
    private function getExpectedPaymentContext(string $orderTransactionId, Context $context): array
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order.currency');
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if (!$orderTransaction instanceof OrderTransactionEntity || !$orderTransaction->getOrder() instanceof OrderEntity) {
            throw PaymentException::syncProcessInterrupted($orderTransactionId, 'Order transaction data is missing for verification.');
        }

        $order = $orderTransaction->getOrder();
        $currency = strtoupper((string) ($order->getCurrency()?->getIsoCode() ?? ''));
        if ($currency === '') {
            throw PaymentException::syncProcessInterrupted($orderTransactionId, 'Order currency is missing for verification.');
        }

        $amountMinor = $this->toMinorUnit((float) $order->getAmountTotal(), $currency);

        return [
            'amountMinor' => $amountMinor,
            'currency' => $currency,
        ];
    }

    private function toMinorUnit(float $amount, string $currency): int
    {
        $zeroDecimalCurrencies = ['JPY', 'KRW', 'VND', 'CLP', 'UGX', 'XAF', 'XOF', 'KMF'];

        if (\in_array(strtoupper($currency), $zeroDecimalCurrencies, true)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }
}
