<?php

namespace SquarePayments\Service;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\Transition;
use Square\Environment;
use Square\Models\CreateWebhookSubscriptionRequest;
use Square\Models\WebhookSubscription;
use Square\SquareClient;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use SquarePayments\Core\Content\Transaction\SquarePaymentsTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;

class WebHookService
{
    private SquareApiFactory $client;
    /** @var EntityRepository<SquarePaymentsTransactionCollection> */
    private EntityRepository $transactionRepository;
    /** @var EntityRepository<OrderTransactionCollection> */
    private EntityRepository $orderTransactionRepository;
    private StateMachineRegistry $stateMachineRegistry;
    private SquareConfigService $squareConfigService;
    private ?LoggerInterface $logger;

    /**
     * @param EntityRepository<SquarePaymentsTransactionCollection> $transactionRepository
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     */
    public function __construct(
        SquareApiFactory $client,
        EntityRepository $transactionRepository,
        EntityRepository $orderTransactionRepository,
        StateMachineRegistry $stateMachineRegistry,
        SquareConfigService $squareConfigService,
        ?LoggerInterface $logger = null
    ) {
        $this->client = $client;
        $this->transactionRepository = $transactionRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->squareConfigService = $squareConfigService;
        $this->logger = $logger;
    }

    public function accept(Request $request, Context $context): bool
    {
        $payload = json_decode($request->getContent(), true);
        if (!$payload || !isset($payload['type'])) {
            // Invalid payload
            return false;
        }
        switch ($payload['type']) {
            case 'payment.created':
            case 'payment.updated':
                return $this->handlePayment($payload, $context);
            case 'refund.created':
            case 'refund.updated':
                return $this->handleRefund($payload, $context);
            default:
                // Unknown event type
                return false;
        }
    }

    private function syncOrderPaymentStatusWithWebhook(string $paymentId, string $webhookStatus, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('transactionId', $paymentId));
        $transactionEntity = $this->transactionRepository->search($criteria, $context)->first();
        if (!$transactionEntity) {
            $this->logger?->warning('No Square transaction found for payment ID: ' . $paymentId);
            return false;
        }
        $orderId = $transactionEntity->getOrderId() ?? null;
        if (!$orderId) {
            $this->logger?->warning('No order ID found for payment ID: ' . $paymentId);
            return false;
        }
        $orderTransactionCriteria = new Criteria();
        $orderTransactionCriteria->addFilter(new EqualsFilter('orderId', $orderId));
        $orderTransactionCriteria->addAssociation('stateMachineState');
        $orderTransaction = $this->orderTransactionRepository->search($orderTransactionCriteria, $context)->first();
        if (!$orderTransaction) {
            $this->logger?->warning('No order transaction found for order ID: ' . $orderId);
            return false;
        }
        $stateMachineState = $orderTransaction->getStateMachineState();
        $currentStatus = $stateMachineState ? $stateMachineState->getTechnicalName() : null;
        if (!$currentStatus) {
            $this->logger?->warning('No state machine state found for order transaction ID: ' . ($orderTransaction->getUniqueIdentifier() ?: 'unknown'));
            return false;
        }
        $toState = $this->mapWebhookStatusToShopware($webhookStatus);
        if ($currentStatus === $toState) {
            return true;
        }
        if ($currentStatus == 'refunded' || $currentStatus == 'cancelled') {
            return true;
        }
        if (!$toState) {
            $this->logger?->warning('Unknown webhook status: ' . $webhookStatus);
            return false;
        }
        if ($toState == 'refunded' && $currentStatus != 'paid') {
            $this->logger?->warning('Cannot transition to refunded from current status: ' . $currentStatus);
            return false;
        }
        if ($toState == 'paid' && $currentStatus != 'authorized') {
            $this->logger?->warning('Cannot transition to paid from current status: ' . $currentStatus);
            return false;
        }
        try {
            $orderTransactionId = $orderTransaction->getId() ?: null;
            if (!$orderTransactionId) {
                $this->logger?->warning('No ID found for order transaction entity.');
                return false;
            }
            $translation = new Transition(
                'order_transaction',
                $orderTransactionId,
                $toState,
                'stateId'
            );
            $this->stateMachineRegistry->transition($translation, $context);
            $this->logger?->info('Order transaction status updated for order ID: ' . $orderId . ' to ' . $toState);
            return true;
        } catch (\Exception $e) {
            $this->logger?->error('Failed to update order transaction status: ' . $e->getMessage());
            return false;
        }
    }

    private function mapWebhookStatusToShopware(string $webhookStatus): ?string
    {
        $map = [
            'APPROVED' => OrderTransactionStates::STATE_AUTHORIZED,
            'COMPLETED' => OrderTransactionStates::STATE_PAID,
            'VOIDED' => OrderTransactionStates::STATE_CANCELLED,
            'REFUNDED' => OrderTransactionStates::STATE_REFUNDED,
        ];
        return $map[$webhookStatus] ?? null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function handlePayment(array $payload, Context $context): bool
    {
        $data = $payload['data']['object']['payment'] ?? null;
        if (!$data) {
            return false;
        }
        $fields = [
            'payment_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
        ];
        $this->syncOrderPaymentStatusWithWebhook($fields['payment_id'], $fields['status'], $context);
        return !empty($fields['payment_id']);
    }
    /**
     * @param array<string,mixed> $payload
     */
    private function handleRefund(array $payload, Context $context): bool
    {
        $data = $payload['data']['object']['refund'] ?? null;
        if (!$data) {
            return false;
        }
        $fields = [
            'refund_id' => $data['id'] ?? null,
            'payment_id' => $data['payment_id'] ?? null,
            'status' => $data['status'] ?? null,
        ];
        $this->syncOrderPaymentStatusWithWebhook($fields['payment_id'], $fields['status'], $context);
        return !empty($fields['refund_id']);
    }

    /**
     * @return array<string,mixed>
     */
    public function save(Request $request, string $environment): array
    {
        $baseUrl = $request->getSchemeAndHttpHost();
        $notificationUrl = rtrim($baseUrl, '/') . '/squarepayments/webhook/accept';
        $webhookSubscriptions = new WebhookSubscription();
        $webhookSubscriptions->setEventTypes([
            "payment.updated",
            "refund.created",
            "refund.updated",
        ]);
        $webhookSubscriptions->setName("Payment Status");
        $webhookSubscriptions->setNotificationUrl($notificationUrl);
        $body = new CreateWebhookSubscriptionRequest($webhookSubscriptions);
        $squareClient = $this->client->createClientByEnv($environment);
        $response = $squareClient->getWebhookSubscriptionsApi()->createWebhookSubscription($body);
        $result = $response->getResult();
        if (is_array($result) && isset($result['errors'])) {
            return ['success' => false, 'message' => $result['errors'][0]->detail ?? 'Unknown error'];
        }
        if ($response->isSuccess() && $result->getSubscription()) {
            $this->squareConfigService->set($environment . 'WebhookSubscriptionId', $result->getSubscription()->getId());
            return ['success' => true, 'webhookId' => $result->getSubscription()->getId()];
        }
        return ['success' => false, 'message' => 'Failed to create webhook subscription'];
    }

    public function delete(string $environment): bool
    {
        $webhookId = $this->squareConfigService->get($environment . 'WebhookSubscriptionId');
        if (!$webhookId) {
            $this->logger?->warning('No webhook subscription ID found in config.');
            return false;
        }
        $squareClient = $this->client->createClientByEnv($environment);
        $response = $squareClient->getWebhookSubscriptionsApi()->deleteWebhookSubscription($webhookId);
        return $response->getStatusCode() === 200;
    }

    /**
     * @return array<string,mixed>
     */
    public function getStatus(string $environment): array
    {
        $webhookId = $this->squareConfigService->get($environment . 'WebhookSubscriptionId');
        if (!$webhookId) {
            return ['status' => 'not configured'];
        }
        $squareClient = $this->client->createClientByEnv($environment);
        $response = $squareClient->getWebhookSubscriptionsApi()->retrieveWebhookSubscription($webhookId);
        $result = $response->getResult();
        if (is_array($result) && isset($result['errors'])) {
            return ['success' => false, 'message' => $result['errors'][0]->detail ?? 'Unknown error'];
        }
        if ($response->isSuccess() && $result->getSubscription()) {
            $this->squareConfigService->set($environment . 'WebhookSubscriptionId', $result->getSubscription()->getId());
            return ['active' => true, 'webhookId' => $result->getSubscription()->getId()];
        }
        return ['active' => false, 'message' => 'Failed to create webhook subscription'];
    }
}
