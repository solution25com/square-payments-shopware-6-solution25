<?php

declare(strict_types=1);

namespace SquarePayments\Storefront\Service;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SquarePayments\Core\Content\SquareSubscriptionCardChoice\SquareSubscriptionCardChoiceEntity;

class SquareSubscriptionOrderProvider
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $squareSubscriptionCardChoiceRepository,
        private readonly SquareSubscriptionIdExtractor $subscriptionIdExtractor,
    ) {
    }

    /**
     * @return array<int, array{
     *   subscriptionId:string,
     *   lastOrderId:string,
     *   lastOrderNumber:string,
     *   lastOrderDateHuman:string|null,
     *   lastOrderAmount:float|null,
     *   lastOrderCurrencyIsoCode:string|null,
     *   paymentState:string|null,
     *   chosenSquareCardId:string|null
     * }>
     */
    public function getSubscriptionRows(SalesChannelContext $salesChannelContext): array
    {
        $customer = $salesChannelContext->getCustomer();
        if (!$customer) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderCustomer.customerId', $customer->getId()));
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addSorting(new FieldSorting('orderDateTime', FieldSorting::DESCENDING));

        $orders = $this->orderRepository->search($criteria, $salesChannelContext->getContext());

        $latestBySubscription = [];
        foreach ($orders as $order) {
            /** @var OrderEntity $order */
            $customFields = $order->getCustomFields() ?? [];
            $isRecurring = $customFields['isRecurringOrder'] ?? null;
            if (!($isRecurring === true || $isRecurring === 1 || $isRecurring === '1' || $isRecurring === 'true')) {
                continue;
            }

            $tx = $order->getTransactions()?->first();
            $txCustomFields = $tx?->getCustomFields() ?? [];

            $isSquareOrder = false;

            $pm = $tx?->getPaymentMethod();
            $handlerIdentifier = $pm?->getHandlerIdentifier();
            if (\is_string($handlerIdentifier) && str_contains($handlerIdentifier, 'Square')) {
                $isSquareOrder = true;
            }

            /* @phpstan-ignore-next-line  */
            if (!$isSquareOrder && \is_array($txCustomFields)) {
                $recurringPayment = \is_array($txCustomFields['recurringPayment'] ?? null) ? (array) $txCustomFields['recurringPayment'] : [];
                $meta = \is_array($recurringPayment['meta'] ?? null) ? (array) $recurringPayment['meta'] : [];
                if (($meta['provider'] ?? null) === 'square') {
                    $isSquareOrder = true;
                }
            }

            if (!$isSquareOrder) {
                continue;
            }

            $subscriptionId = $this->subscriptionIdExtractor->extract($order);
            if (!\is_string($subscriptionId) || !Uuid::isValid($subscriptionId)) {
                continue;
            }

            if (!isset($latestBySubscription[$subscriptionId])) {
                $latestBySubscription[$subscriptionId] = $order;
            }
        }

        if ($latestBySubscription === []) {
            return [];
        }

        $subscriptionIds = array_keys($latestBySubscription);

        $choiceCriteria = new Criteria();
        $choiceCriteria->addFilter(new EqualsFilter('customerId', $customer->getId()));
        $choiceCriteria->addFilter(new EqualsAnyFilter('subscriptionId', $subscriptionIds));
        $choices = $this->squareSubscriptionCardChoiceRepository->search($choiceCriteria, $salesChannelContext->getContext());

        $choiceBySubscription = [];
        foreach ($choices as $choice) {
            /** @var SquareSubscriptionCardChoiceEntity $choice */
            $choiceBySubscription[$choice->getSubscriptionId()] = $choice->getSquareCardId();
        }

        $rows = [];
        foreach ($latestBySubscription as $subscriptionId => $order) {
            $tx = $order->getTransactions()?->first();
            $state = $tx?->getStateMachineState()?->getTechnicalName();
            $orderDateTime = $order->getOrderDateTime();

            $fallbackCardId = null;
            $txCustomFields = $tx?->getCustomFields() ?? [];
            /* @phpstan-ignore-next-line  */
            if (\is_array($txCustomFields)) {
                $recurringPayment = \is_array($txCustomFields['recurringPayment'] ?? null) ? (array) $txCustomFields['recurringPayment'] : [];
                $ref = $recurringPayment['reference'] ?? null;
                if (\is_string($ref) && $ref !== '') {
                    $fallbackCardId = $ref;
                }
            }

            $rows[] = [
                'subscriptionId' => (string) $subscriptionId,
                'lastOrderId' => $order->getId(),
                'lastOrderNumber' => (string) $order->getOrderNumber(),
                'lastOrderDateHuman' => $orderDateTime->format('d.m.Y H:i'),
                'lastOrderAmount' => $order->getAmountTotal(),
                'lastOrderCurrencyIsoCode' => $order->getCurrency()?->getIsoCode(),
                'paymentState' => $state,
                'chosenSquareCardId' => $choiceBySubscription[$subscriptionId] ?? $fallbackCardId,
            ];
        }

        return $rows;
    }
}
