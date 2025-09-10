<?php declare(strict_types=1);

namespace SquarePayments\Storefront\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\Country\SalesChannel\AbstractCountryRoute;
use Shopware\Core\System\Country\SalesChannel\CountryRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoader;
use SquarePayments\Library\EnvironmentUrl;
use SquarePayments\Service\SquareCardService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class SquareCardController extends StorefrontController
{
    private GenericPageLoader $genericPageLoader;
    private SquareCardService $cardService;

    public function __construct(
        GenericPageLoader $genericPageLoader,
        SquareCardService $cardService,
        private readonly AbstractCountryRoute $countryRoute
    ) {
        $this->genericPageLoader = $genericPageLoader;
        $this->cardService = $cardService;
    }

    #[Route(
        path: '/account/squarepayments/saved-cards',
        name: 'frontend.squarepayments.saved_cards',
        methods: ['GET']
    )]
    public function savedCards(Request $request, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();
        if (!$customer) {
            return $this->redirectToRoute('frontend.account.login');
        }
        $page = $this->genericPageLoader->load($request, $context);
        $cards = $this->cardService->getSavedCards($context)['cards'] ?? [];

        $criteria = (new Criteria())
            ->addAssociation('states')
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position'));

        $countries = $this->countryRoute
            ->load($request, $criteria, $context)
            ->getCountries();
        // Map countries to use ISO code as key
        $countriesByIso = [];
        foreach ($countries as $country) {
            $iso = $country->getIso();
            if ($iso) {
                $countriesByIso[$iso] = $country;
            }
        }
        $templateVariables = new ArrayStruct([
            'countries' => $countriesByIso,
            'productionJS' => EnvironmentUrl::SQUARE_JS_LIVE->value,
            'sandboxJS' => EnvironmentUrl::SQUARE_JS_SANDBOX->value,
        ]);

        $page->addExtension(    'squarepayments',
            $templateVariables);



        return $this->renderStorefront('@Storefront/storefront/page/account/saved_cards.html.twig', [
            'page' => $page,
            'cards' => $cards,
            'paymentMethodId' => $context->getPaymentMethod()->getId()
        ]);
    }

    #[Route(
        path: '/squarepayments/get-saved-cards',
        name: 'squarepayments.get_saved_cards',
        defaults: ['_routeScope' => ['storefront']],
        methods: ['GET']
    )]
    public function getSavedCards(SalesChannelContext $context): JsonResponse
    {
        return new JsonResponse($this->cardService->getSavedCards($context));
    }

    #[Route(
        path: '/account/squarepayments/add-card',
        name: 'squarepayments.add_card',
        methods: ['POST']
    )]
    public function addCard(Request $request, SalesChannelContext $context): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $data = $this->cardService->addCard($request, $context);
        if (isset($data['errors'])) {
            return new JsonResponse(['success' => false, 'message' => 'Add Card Failed']);
        }
        return new JsonResponse(['success' => true, 'message' => 'Card added successfully']);
    }

    #[Route(
        path: '/account/squarepayments/delete-card/{cardId}',
        name: 'squarepayments.delete_card',
        methods: ['POST']
    )]
    public function deleteCard(string $cardId, Request $request, SalesChannelContext $context): \Symfony\Component\HttpFoundation\JsonResponse
    {
        try {
            $result = $this->cardService->deleteCard($cardId, $context);
            if (isset($result['errors'])) {
                return new JsonResponse(['success' => false, 'message' => 'Delete Card Failed']);
            }
            return new JsonResponse(['success' => true, 'message' => 'Card deleted successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Delete Card Failed: ' . $e->getMessage()]);
        }
    }
}
