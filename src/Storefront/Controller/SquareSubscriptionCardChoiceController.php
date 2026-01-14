<?php

declare(strict_types=1);

namespace SquarePayments\Storefront\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use SquarePayments\Core\Content\SquareSubscriptionCardChoice\SquareSubscriptionCardChoiceEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class SquareSubscriptionCardChoiceController extends StorefrontController
{
    public function __construct(
        private readonly EntityRepository $squareSubscriptionCardChoiceRepository,
    ) {
    }

    #[Route(
        path: '/account/squarepayments/subscription-card-choice',
        name: 'frontend.squarepayments.subscription_card_choice.save',
        defaults: ['XmlHttpRequest' => true, '_loginRequired' => true],
        methods: ['POST']
    )]
    public function save(Request $request, SalesChannelContext $context): JsonResponse
    {
        $customer = $context->getCustomer();
        if (!$customer) {
            return new JsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $subscriptionId = (string) $request->request->get('subscriptionId');
        $cardId = (string) $request->request->get('cardId');

        if (!Uuid::isValid($subscriptionId)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid subscriptionId'], 400);
        }

        if ($cardId === '') {
            return new JsonResponse(['success' => false, 'message' => 'Missing cardId'], 400);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));
        $criteria->addFilter(new EqualsFilter('subscriptionId', $subscriptionId));

        /** @var SquareSubscriptionCardChoiceEntity|null $existing */
        $existing = $this->squareSubscriptionCardChoiceRepository
            ->search($criteria, $context->getContext())
            ->first();

        $payload = [
            'id' => $existing?->getId() ?? Uuid::randomHex(),
            'customerId' => $customer->getId(),
            'subscriptionId' => $subscriptionId,
            'squareCardId' => $cardId,
        ];

        $this->squareSubscriptionCardChoiceRepository->upsert([$payload], $context->getContext());

        return new JsonResponse(['success' => true]);
    }
}
