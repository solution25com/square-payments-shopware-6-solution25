<?php

namespace SquarePayments\Gateways;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use SquarePayments\Library\TransactionStatuses;
use SquarePayments\Service\SquarePaymentsTransactionService;
use SquarePayments\Service\SquareConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Checkout\Payment\PaymentException;

class ApplePay extends AbstractPaymentHandler
{
    private OrderTransactionStateHandler $transactionStateHandler;
    private SquarePaymentsTransactionService $squarePaymentsTransactionService;
    /** @var EntityRepository<OrderTransactionCollection> */
    private EntityRepository $orderTransactionRepository;
    /** @var EntityRepository<PaymentMethodCollection> */
    private EntityRepository $paymentMethodRepository;

    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        SquarePaymentsTransactionService $squarePaymentsTransactionService,
        EntityRepository $orderTransactionRepository,
        EntityRepository $paymentMethodRepository,
        private readonly SquareConfigService $squareConfigService
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->squarePaymentsTransactionService = $squarePaymentsTransactionService;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * @inheritDoc
     */
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
            throw PaymentException::syncProcessInterrupted($transaction->getOrderTransactionId(), 'Square payment method is not configured for this sales channel.');
        }

        $dataBag = new RequestDataBag($request->request->all());
        $squarePaymentsTransactionId = $dataBag->get('squarepayments_transaction_id');
        if (!\is_string($squarePaymentsTransactionId) || $squarePaymentsTransactionId === '') {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw PaymentException::syncProcessInterrupted($transaction->getOrderTransactionId(), 'Missing Square transaction id.');
        }
        $ext = $context->getExtension('paymentMethodName');
        if ($ext instanceof ArrayStruct) {
            $paymentMethodName = (string)($ext->get('paymentMethodName') ?? '');
        } else {
            $paymentMethodName = '';
        }
        $orderTransactionId = $transaction->getOrderTransactionId();

        $orderId = $this->getOrderIdFromTransaction($transaction->getOrderTransactionId(), $context);
        $this->transactionStateHandler->paid(
            $orderTransactionId,
            $context
        );
        $this->squarePaymentsTransactionService->addTransaction(
            $orderId,
            $paymentMethodName,
            $squarePaymentsTransactionId,
            TransactionStatuses::PAID->value,
            $context
        );
        return null;
    }

    public function supports(
        PaymentHandlerType $type,
        string $paymentMethodId,
        Context $context
    ): bool {
        return $paymentMethodId === $this->getPaymentMethodId($context);
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
}
