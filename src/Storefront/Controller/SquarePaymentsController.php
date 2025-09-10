<?php declare(strict_types=1);

namespace SquarePayments\Storefront\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoader;
use SquarePayments\Service\SquareCardService;
use SquarePayments\Service\SquarePaymentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class SquarePaymentsController extends StorefrontController
{
    public function __construct(
        readonly GenericPageLoader    $genericPageLoader,
        readonly SquarePaymentService $paymentService
    )
    {
    }
    #[Route(
        path: '/squarepayments/authorize-payment',
        name: 'squarepayments.authorize_payment',
        defaults: ['_routeScope' => ['storefront']],
        methods: ['POST']
    )]
    public function authorizePayment(Request $request, SalesChannelContext $context): JsonResponse
    {
        return new JsonResponse($this->paymentService->authorizePayment($request, $context));
    }
}
