<?php

namespace SquarePayments\Controller;

use Shopware\Core\Framework\Context;
use SquarePayments\Service\SquarePaymentService;
use SquarePayments\Service\WebHookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use SquarePayments\Service\SquareApiTestService;

#[Route(defaults: ['_routeScope' => ['api']])]
class SquarePaymentsApiController extends AbstractController
{
    public function __construct(
        private readonly SquareApiTestService $apiTestService,
        private readonly SquarePaymentService $paymentService,
        private readonly WebHookService $webHookService
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

    #[Route(
        path: '/api/_action/squarepayments/webhook/status',
        name: 'api.squarepayments.webhook.status',
        methods: ['GET'],
        defaults: ['_acl' => ['system_config:read']]
    )]
    public function webhookStatus(Request $request): JsonResponse
    {
        $environment = $this->resolveEnvironment($request);
        if ($environment === null) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid environment'], 400);
        }

        return new JsonResponse($this->webHookService->getStatus($environment));
    }

    #[Route(
        path: '/api/_action/squarepayments/webhook/create',
        name: 'api.squarepayments.webhook.create',
        methods: ['POST'],
        defaults: ['_acl' => ['system_config:write']]
    )]
    public function webhookCreate(Request $request): JsonResponse
    {
        $environment = $this->resolveEnvironment($request);
        if ($environment === null) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid environment'], 400);
        }

        return new JsonResponse($this->webHookService->save($request, $environment));
    }

    #[Route(
        path: '/api/_action/squarepayments/webhook/delete',
        name: 'api.squarepayments.webhook.delete',
        methods: ['POST'],
        defaults: ['_acl' => ['system_config:write']]
    )]
    public function webhookDelete(Request $request): JsonResponse
    {
        $environment = $this->resolveEnvironment($request);
        if ($environment === null) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid environment'], 400);
        }

        $result = $this->webHookService->delete($environment);
        $response = [
            'success' => $result,
            'message' => $result ? 'Webhook deleted successfully' : 'Failed to delete webhook',
        ];

        return new JsonResponse($response);
    }

    private function resolveEnvironment(Request $request): ?string
    {
        $environment = trim((string) $request->query->get('environment'));

        if ($environment === '') {
            $payload = json_decode($request->getContent(), true);
            if (\is_array($payload)) {
                $environment = trim((string) ($payload['environment'] ?? ''));
            }
        }

        if (!\in_array($environment, ['sandbox', 'production'], true)) {
            return null;
        }

        return $environment;
    }
}
