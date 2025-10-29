<?php

namespace SquarePayments\Storefront\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use SquarePayments\Service\WebHookService;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class WebHookController extends StorefrontController
{
    private WebHookService $webHookService;

    public function __construct(WebHookService $webHookService)
    {
        $this->webHookService = $webHookService;
    }

    #[Route('/squarepayments/webhook/accept', name: 'squarepayments.webhook.accept', methods: ['POST'])]
    public function acceptWebhook(Request $request, SalesChannelContext $context): Response
    {
        $result = $this->webHookService->accept($request, $context->getContext());
        return new Response($result ? 'Webhook accepted' : 'Webhook failed', $result ? 200 : 400);
    }

    #[Route('/squarepayments/webhook/status', name: 'squarepayments.webhook.status', methods: ['GET'])]
    public function statusWebhook(Request $request): JsonResponse
    {
        $environment = (string) $request->query->get('environment');
        $status = $this->webHookService->getStatus($environment);
        return new JsonResponse($status);
    }

    #[Route('/squarepayments/webhook/create', name: 'squarepayments.webhook.create', methods: ['POST'])]
    public function createWebhook(Request $request): JsonResponse
    {
        $environment = (string) $request->query->get('environment');
        $result = $this->webHookService->save($request, $environment);
        return new JsonResponse($result);
    }

    #[Route('/squarepayments/webhook/delete', name: 'squarepayments.webhook.delete', methods: ['POST'])]
    public function deleteWebhookApi(Request $request): JsonResponse
    {
        $environment = (string) $request->query->get('environment');
        $result = $this->webHookService->delete($environment);
        $response = [
            'success' => $result,
            'message' => $result ? 'Webhook deleted successfully' : 'Failed to delete webhook',
        ];
        return new JsonResponse($response);
    }
}
