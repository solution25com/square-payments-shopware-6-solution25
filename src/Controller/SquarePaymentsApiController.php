<?php

namespace SquarePayments\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SquarePayments\Service\SquarePaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use SquarePayments\Service\SquareApiTestService;

#[Route(defaults: ['_routeScope' => ['api']])]
class SquarePaymentsApiController extends AbstractController
{
    public function __construct(
        private readonly SquareApiTestService $apiTestService,
        private readonly SquarePaymentService $paymentService
    ) {
    }

    #[Route(path: '/api/_action/square-payments/config', name: 'api.square_payments.config', methods: ['GET'])]
    public function getConfig(Request $request): JsonResponse
    {

        return new JsonResponse(['status' => 'ok', 'config' => []]);
    }


    #[Route(path: '/api/_action/square-payments/transactions', name: 'api.square_payments.transactions', methods: ['GET'])]
    public function getTransactions(Request $request): JsonResponse
    {
        // Return transaction list (stub)
        return new JsonResponse(['status' => 'ok', 'transactions' => []]);
    }

    #[Route(
        path: '/api/_action/squarepayments/api-test/check',
        name: 'squarepayments.api_test.check',
        methods: ['POST'],
        defaults: ['_acl' => ['system_config:read']]
    )]
    public function check(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $result = $this->apiTestService->checkLocation($payload);
        return new JsonResponse($result);
    }
    #[Route(
        path: '/api/_action/squarepayments/requiring-payment',
        name: 'api.squarepayments.requiring_payment',
        methods: ['POST'],
        defaults: ['_acl' => ['square_payments:write']]
    )]
    public function requiringPayment(Request $request, Context $context): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $cardId = $payload['cardId'] ?? null;
        $orderId = $payload['orderId'] ?? null;
        $squareCustomerId = $payload['squareCustomerId'] ?? null;
        return new JsonResponse($this->paymentService->requiringPayment($cardId, $orderId, $squareCustomerId, $context));
    }
}
