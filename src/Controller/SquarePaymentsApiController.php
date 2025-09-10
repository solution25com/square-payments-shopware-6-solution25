<?php

namespace SquarePayments\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use SquarePayments\Service\SquareApiTestService;

#[Route(defaults: ['_routeScope' => ['api']])]
class SquarePaymentsApiController extends AbstractController
{
    private SquareApiTestService $apiTestService;

    public function __construct(SquareApiTestService $apiTestService)
    {
        $this->apiTestService = $apiTestService;
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

    #[Route('/api/_action/squarepayments/api-test/check', name: 'squarepayments.api_test.check', methods: ['POST'])]
    public function check(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $result = $this->apiTestService->checkLocation($payload);
        return new JsonResponse($result);
    }
}
