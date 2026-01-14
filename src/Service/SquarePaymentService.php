<?php

namespace SquarePayments\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Square\Models\Address;
use Square\Models\Money;
use Square\SquareClient;
use Square\Models\CreatePaymentRequest;
use Square\Models\CompletePaymentRequest;
use SquarePayments\Library\TransactionStatuses;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Checkout\Order\OrderCollection;

class SquarePaymentService
{
    private SquareClient $client;
    private ResponseHandleService $responseHandleService;
    private SquareCardService $cardService;
    private SquareApiFactory $squareApiFactory;
    private SquareConfigService $squareConfigService;

    private LoggerInterface $logger;
    private OrderTransactionStateHandler $transactionStateHandler;
    public SquarePaymentsTransactionService $transactionService;
    /** @var EntityRepository<OrderCollection> */
    private EntityRepository $orderRepository;
    private TransactionLogger $transactionLogger;
    /** @param EntityRepository<OrderCollection> $orderRepository */
    public function __construct(
        SquareConfigService $squareConfigService,
        SquareApiFactory $client,
        ResponseHandleService $responseHandleService,
        SquareCardService $cardService,
        LoggerInterface $logger,
        OrderTransactionStateHandler $transactionStateHandler,
        SquarePaymentsTransactionService $transactionService,
        EntityRepository $orderRepository,
        TransactionLogger $transactionLogger
    ) {
        $this->squareConfigService = $squareConfigService;
        $this->squareApiFactory = $client;
        $this->client = $client->create();
        $this->responseHandleService = $responseHandleService;
        $this->cardService = $cardService;
        $this->logger = $logger;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->transactionService = $transactionService;
        $this->orderRepository = $orderRepository;
        $this->transactionLogger = $transactionLogger;
    }

    /** @return array<string,mixed> */
    public function capturePayment(string $paymentId, ?CompletePaymentRequest $body = null): array
    {
        try {
            $response = $this->client->getPaymentsApi()->completePayment(
                $paymentId,
                $body ?? new CompletePaymentRequest()
            );
            $result = $response->getResult();
            return $this->responseHandleService->process($result);
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /** @return array<string,mixed> */
    public function voidPayment(string $paymentId): array
    {
        try {
            $response = $this->client->getPaymentsApi()->cancelPayment($paymentId);
            $result = $response->getResult();
            return $this->responseHandleService->process($result);
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /** @return array<string,mixed> */
    public function authorizePayment(Request $request, SalesChannelContext $context): array
    {
        $this->logger->debug('start of authorizePayment');
        $this->logger->debug('authorizepayment request', [$request->request->all()]);
        $data = $this->parsePayload($request);
        $this->logger->debug("beginning payload", ['payload' => $data]);

        $sourceId = $data['paymentToken'] ?? $data['cardId'] ?? null;

        if (!$sourceId) {
            return ['status' => 'error', 'message' => 'Source ID is required'];
        }
        $isSubscription = isset($data['isSubscription']) ? (bool)$data['isSubscription'] : false;
        $customer = $context->getCustomer();
        $idempotencyKey = $this->generateIdempotencyKey();
        $squareCustomerId = null;
        if ($customer) {
            $squareCustomerId = $this->cardService->getOrCreateSquareCustomerId(
                $customer->getId(),
                $data,
                $context->getContext()
            );
        }
        $currency = strtoupper(
            $data['currency'] ?? $context->getCurrency()->getIsoCode()
        );

        if (isset($data['minorAmount']) && ($data['minorAmount'] === true || $data['minorAmount'] === 1 || $data['minorAmount'] === '1')) {
            $amountMinor = (int)($data['amount'] ?? 0);
        } else {
            $amountMinor = $this->toMinorUnit($data['amount'], $currency);
        }

        $money = new Money();
        $money->setAmount($amountMinor);
        $money->setCurrency($currency);

        $paymentRequest = new CreatePaymentRequest($sourceId, $idempotencyKey);
        $paymentRequest->setCustomerId($squareCustomerId);
        $paymentRequest->setAmountMoney($money);
        $paymentRequest->setLocationId($this->squareApiFactory->getLocationId());
        $paymentMode = $this->squareConfigService->get('paymentMode');
        $paymentRequest->setAutocomplete($paymentMode === 'AUTHORIZE_AND_CAPTURE');

        try {
            $response = $this->client->getPaymentsApi()->createPayment($paymentRequest);
            $result = $response->getResult();
            $processed = $this->responseHandleService->process($result);
            $this->logger->debug("completed payload", ['processed' => $processed]);

            if ($processed['status'] === 'success') {
                if (
                    (isset($data['saveCard']) && $data['saveCard']) ||
                    (empty($data['cardId']) && $isSubscription)
                ) {
                    $customerName = trim(($customer?->getFirstName() ?? '') . ' ' . ($customer?->getLastName() ?? ''));
                    $payload = [
                        'sourceId' => $sourceId,
                        'paymentId' => $processed['payment']['id'] ?? '',
                        'cardholderName' => $customerName
                    ];
                    $addCardResult = $this->cardService->addCard(new Request([], $payload), $context);

                    if ($addCardResult && array_key_exists('card', $addCardResult)) {
                        $processed['card'] = json_decode((string)json_encode($addCardResult['card']), true);
                    }
                    $this->logger->debug("Add Card result", ['result' => $addCardResult]);
                } elseif ($isSubscription && !empty($data['cardId'])) {
                    $cardDetails = $this->cardService->getSavedCard($context, $data['cardId']);
                    if ($cardDetails && array_key_exists('card', $cardDetails)) {
                        $processed['card'] = json_decode((string)json_encode($cardDetails['card']), true);
                    }
                }
            }
            return $processed;
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /** @return array<string,mixed> */
    public function requiringPayment(string $cardId, string $orderId, string $squareCustomerId, Context $context): array
    {
        if (!$cardId || !$orderId || !$squareCustomerId) {
            return ['status' => 'error', 'message' => 'Card ID, Customer Id and Order ID are required'];
        }
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('currency');
        $criteria->addAssociation('transactions');
        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order) {
            return ['status' => 'error', 'message' => 'Order not found'];
        }

        $orderTransactionId = $this->getLatestOrderTransactionId($order);
        if (!$orderTransactionId) {
            $this->logger->error('No order transaction found for order', ['orderId' => $orderId]);
            return ['status' => 'error', 'message' => 'Order has no transactions'];
        }

        $idempotencyKey = $this->generateIdempotencyKey();
        $currency = strtoupper($order->getCurrency()?->getIsoCode() ?? 'USD');
        $amountTotal = $order->getAmountTotal();
        $amountMinor = $this->toMinorUnit($amountTotal, $currency);

        $money = new Money();
        $money->setAmount($amountMinor);
        $money->setCurrency($currency);

        $paymentRequest = new CreatePaymentRequest($cardId, $idempotencyKey);
        $paymentRequest->setCustomerId($squareCustomerId);
        $paymentRequest->setAmountMoney($money);
        $paymentRequest->setLocationId($this->squareApiFactory->getLocationId());
        $paymentMode = $this->squareConfigService->get('paymentMode');
        $paymentRequest->setAutocomplete($paymentMode === 'AUTHORIZE_AND_CAPTURE');

        try {
            $response = $this->client->getPaymentsApi()->createPayment($paymentRequest);
            $result = $response->getResult();
            $processed = $this->responseHandleService->process($result);

            if ($processed['status'] === 'success') {
                $transactionStatus = $paymentMode === 'AUTHORIZE_AND_CAPTURE' ? TransactionStatuses::PAID->value : TransactionStatuses::AUTHORIZED->value;

                if ($paymentMode === 'AUTHORIZE_AND_CAPTURE') {
                    $this->transactionStateHandler->paid($orderTransactionId, $context);
                } else {
                    $this->transactionStateHandler->authorize($orderTransactionId, $context);
                }

                $squareTransactionId = $this->transactionService->addTransaction(
                    $orderId,
                    'Credit Card',
                    $processed['payment']['id'],
                    $transactionStatus,
                    $context
                );
                $paymentData = is_array($processed['payment']) ? $processed['payment'] : json_decode((string)$processed['payment'], true);
                $this->transactionLogger->logTransaction($transactionStatus, $paymentData, $orderId, $context, $squareTransactionId);

                return ['status' => 'success', 'message' => 'Payment processed successfully'];
            }

            return ['status' => 'error', 'message' => 'Payment failed', 'details' => $processed];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /** @return array<string,mixed> */
    private function parsePayload(Request $request): array
    {
        $contentType = (string)$request->headers->get('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            $content = $request->getContent();
            if ($content !== '') {
                try {
                    $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                    if (\is_array($decoded)) {
                        return $decoded;
                    }
                } catch (\Throwable) {
                }
            }
        }

        return $request->request->all();
    }

    private function toMinorUnit(null|int|float|string $amount, string $currency): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        if (\is_string($amount)) {
            $amount = str_replace([','], ['.'], $amount);
        }

        $value = (float)$amount;
        $zeroDecimalCurrencies = ['JPY', 'KRW', 'VND', 'CLP', 'UGX', 'XAF', 'XOF', 'KMF'];
        $isZeroDecimal = \in_array(strtoupper($currency), $zeroDecimalCurrencies, true);

        return $isZeroDecimal ? (int)round($value) : (int)round($value * 100);
    }

    public function generateIdempotencyKey(): string
    {
        $data = random_bytes(16);

        // Set the version to 0100 (v4)
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // Set bits 6–7 to 10 (variant)
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }


    public function isSubscriptionOrder(
        OrderEntity $order
    ): bool {
        $isSubscription = $order->getExtensionOfType('foreignKeys', ArrayStruct::class)?->get(
            'subscriptionId'
        ) !== null || $order->getExtensionOfType('foreignKeys', ArrayStruct::class)?->get(
            's25SubscriptionId'
        ) !== null;

        return $isSubscription;
    }

    private function getLatestOrderTransactionId(OrderEntity $order): ?string
    {
        $transactions = $order->getTransactions();
        if (!$transactions || $transactions->count() === 0) {
            return null;
        }

        $latest = null;
        foreach ($transactions as $t) {
            if ($latest === null) {
                $latest = $t;
                continue;
            }
            $latestCreatedAt = $latest->getCreatedAt()?->getTimestamp() ?? 0;
            $currentCreatedAt = $t->getCreatedAt()?->getTimestamp() ?? 0;
            if ($currentCreatedAt >= $latestCreatedAt) {
                $latest = $t;
            }
        }

        return $latest?->getId();
    }
    public function processRecurringPayment(
        string $cardId,
        string $orderId,
        string $squareCustomerId,
        int $amountMinor,
        string $currencyIsoCode,
        string $orderTransactionId,
        Context $context
    ): array {
        if ($cardId === '' || $orderId === '' || $squareCustomerId === '') {
            return ['status' => 'error', 'message' => 'Card ID, Customer Id and Order ID are required'];
        }

        $idempotencyKey = $this->generateIdempotencyKey();
        $currency = strtoupper($currencyIsoCode ?: 'USD');

        $money = new Money();
        $money->setAmount($amountMinor);
        $money->setCurrency($currency);

        $paymentRequest = new CreatePaymentRequest($cardId, $idempotencyKey);
        $paymentRequest->setCustomerId($squareCustomerId);
        $paymentRequest->setAmountMoney($money);
        $paymentRequest->setLocationId($this->squareApiFactory->getLocationId());

        $paymentMode = $this->squareConfigService->get('paymentMode');
        $paymentRequest->setAutocomplete($paymentMode === 'AUTHORIZE_AND_CAPTURE');

        try {
            $response = $this->client->getPaymentsApi()->createPayment($paymentRequest);
            $result = $response->getResult();
            $processed = $this->responseHandleService->process($result);

            if (($processed['status'] ?? null) !== 'success') {
                return ['status' => 'error', 'message' => 'Payment failed', 'details' => $processed];
            }

            $paymentId = (string)($processed['payment']['id'] ?? '');
            $transactionStatus = $paymentMode === 'AUTHORIZE_AND_CAPTURE'
                ? TransactionStatuses::PAID->value
                : TransactionStatuses::AUTHORIZED->value;

            if ($paymentMode === 'AUTHORIZE_AND_CAPTURE') {
                $this->transactionStateHandler->paid($orderTransactionId, $context);
            } else {
                $this->transactionStateHandler->authorize($orderTransactionId, $context);
            }

            $squareTransactionId = $this->transactionService->addTransaction(
                $orderId,
                'Credit Card',
                $paymentId,
                $transactionStatus,
                $context
            );

            $paymentData = $processed['payment'] ?? [];
            $paymentData = is_array($paymentData) ? $paymentData : (json_decode((string) $paymentData, true) ?: []);
            $this->transactionLogger->logTransaction($transactionStatus, $paymentData, $orderId, $context, $squareTransactionId);

            return ['status' => 'success', 'paymentId' => $paymentId];
        } catch (\Throwable $e) {
            $this->logger->error('Recurring payment exception', [
                'orderId' => $orderId,
                'orderTransactionId' => $orderTransactionId,
                'message' => $e->getMessage(),
            ]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
