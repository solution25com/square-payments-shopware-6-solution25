<?php

declare(strict_types=1);

namespace SquarePayments\EventSubscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\System\StateMachine\StateMachineException;
use Square\Models\RefundPaymentRequest;
use SquarePayments\Library\TransactionStatuses;
use SquarePayments\Service\SquarePaymentsTransactionService;
use SquarePayments\Service\SquarePaymentService;
use SquarePayments\Service\SquareRefundService;
use SquarePayments\Gateways\CreditCard;
use SquarePayments\Service\TransactionLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Square\Models\Money;

class PaymentStatusSubscriber implements EventSubscriberInterface
{
    /** @var EntityRepository */
    private EntityRepository $orderTransactionRepository;

    private SquarePaymentsTransactionService $transactionService;

    private SquarePaymentService $squarePaymentService;

    private SquareRefundService $squareRefundService;

    private ?LoggerInterface $logger;
    private TransactionLogger $transactionLogger;
    public function __construct(
        EntityRepository $orderTransactionRepository,
        SquarePaymentsTransactionService $transactionService,
        SquarePaymentService $squarePaymentService,
        SquareRefundService $squareRefundService,
        TransactionLogger $transactionLogger,
        ?LoggerInterface $logger = null
    ) {
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->transactionService = $transactionService;
        $this->squarePaymentService = $squarePaymentService;
        $this->squareRefundService = $squareRefundService;
        $this->logger = $logger;
        $this->transactionLogger = $transactionLogger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'onStateChanged',
        ];
    }

    public function onStateChanged(StateMachineTransitionEvent $event): void
    {
        if ($event->getEntityName() !== 'order_transaction') {
            return;
        }

        $transactionId = $event->getEntityId();
        $context = $event->getContext();

        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('paymentMethod');
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.currency');
        $transaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if (!$transaction || $transaction->getPaymentMethod()->getHandlerIdentifier() !== CreditCard::class) {
            return;
        }

        $squareTx = $this->transactionService->getTransactionByOrderId($transaction->getOrder()->getId(), $context);
        if (!$squareTx) {
            $this->logger?->warning('No Square transaction found for order ID: ' . $transaction->getOrder()->getId());
            return;
        }

        $squareTxId = $squareTx->getTransactionId();
        $fromState = $event->getFromPlace()->getTechnicalName();
        $toState = $event->getToPlace()->getTechnicalName();

        switch (true) {
            case $fromState === OrderTransactionStates::STATE_AUTHORIZED && $toState === OrderTransactionStates::STATE_PAID:
                // Capture the payment
                $captureResult = $this->squarePaymentService->capturePayment($squareTxId);
                if ($captureResult['status'] === 'error') {
                    $this->logger?->error('Capture failed for Square transaction ID: ' . $squareTxId . ' - ' . $captureResult['message']);
                    throw new StateMachineException(400, 'SQUAREPAYMENTS_CAPTURE_FAILED', $captureResult['message']);
                }

                $squareTransactionId = $this->transactionService->addTransaction(
                    $transaction->getOrder()->getId(),
                    $transaction->getPaymentMethod()->getName(),
                    $squareTxId,
                    TransactionStatuses::PAID->value,
                    $context
                );
                $this->transactionLogger->logTransaction(TransactionStatuses::PAID->value, $captureResult['payment'], $transaction->getOrder()->getId(), $context, $squareTransactionId);
                break;

            case $fromState === OrderTransactionStates::STATE_AUTHORIZED && $toState === OrderTransactionStates::STATE_CANCELLED:
                // Void the payment
                $voidResult = $this->squarePaymentService->voidPayment($squareTxId);
                if ($voidResult['status'] === 'error') {
                    $this->logger?->error('Void failed for Square transaction ID: ' . $squareTxId . ' - ' . $voidResult['message']);
                    throw new StateMachineException(400, 'SQUAREPAYMENTS_VOID_FAILED', $voidResult['message']);
                }
                $squareTransactionId = $this->transactionService->addTransaction(
                    $transaction->getOrder()->getId(),
                    $transaction->getPaymentMethod()->getName(),
                    $squareTxId,
                    TransactionStatuses::VOIDED->value,
                    $context
                );
                $this->transactionLogger->logTransaction(TransactionStatuses::PAID->value, $voidResult['payment'], $transaction->getOrder()->getId(), $context, $squareTransactionId);
                break;

            case $fromState === OrderTransactionStates::STATE_PAID && $toState === OrderTransactionStates::STATE_REFUNDED:
                // Refund the payment (full refund assumed)
                $amount = $transaction->getAmount()->getTotalPrice();
                $currency = $transaction->getOrder()->getCurrency()->getIsoCode();

                $money = new Money();
                $money->setAmount((int) ($amount * 100));
                $money->setCurrency($currency);

                $refundData = new RefundPaymentRequest($this->squarePaymentService->generateIdempotencyKey(), $money);
                $refundData->setPaymentId($squareTxId);
                $refundResult = $this->squareRefundService->refundPayment($refundData);

                if ($refundResult['status'] === "error") {
                    $this->logger?->error('Refund failed for Square transaction ID: ' . $squareTxId . ' - ' . $refundResult['message']);
                    throw new StateMachineException(400, 'SQUAREPAYMENTS_REFUND_FAILED', $refundResult['message']);
                }

                $squareTransactionId = $this->transactionService->addTransaction(
                    $transaction->getOrder()->getId(),
                    $transaction->getPaymentMethod()->getName(),
                    $squareTxId,
                    TransactionStatuses::REFUND->value,
                    $context
                );
                $customFields = $squareTx->getCustomFields();
                $refundResult['payment']['source_type'] = $customFields['card_category'] ?? null;
                $refundResult['payment']['card_details']['card']['card_brand'] = $customFields['payment_method_type'] ?? null;
                $refundResult['payment']['card_details']['card']['exp_month'] = $customFields['expiry_month'] ?? null;
                $refundResult['payment']['card_details']['card']['exp_year'] = $customFields['expiry_year'] ?? null;
                $refundResult['payment']['card_details']['card']['last_4'] = $customFields['card_last_4'] ?? null;
                $this->transactionLogger->logTransaction(TransactionStatuses::REFUND->value, $refundResult['payment'], $transaction->getOrder()->getId(), $context, $squareTransactionId);
                break;

            default:
                // Other transitions can be handled if needed
                break;
        }
    }
}