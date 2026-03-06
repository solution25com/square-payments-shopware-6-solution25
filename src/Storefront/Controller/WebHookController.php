<?php

namespace SquarePayments\Storefront\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
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
}
