<?php

declare(strict_types=1);

namespace SquarePayments\EventSubscriber;

use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Storefront\Page\GenericPageLoadedEvent;
use SquarePayments\Service\SquareCardService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class S25SubscriptionPaymentMethodProviderSubscriber implements EventSubscriberInterface
{
    private const EXTENSION_NAME = 's25SubscriptionPaymentMethodChange';

    public function __construct(private readonly SquareCardService $cardService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            GenericPageLoadedEvent::class => 'onPageLoaded',
        ];
    }

    public function onPageLoaded(GenericPageLoadedEvent $event): void
    {
        $resolvedUri = (string) $event->getRequest()->attributes->get('resolved-uri');
        if ($resolvedUri !== '/account/s25-subscriptions') {
            return;
        }

        $page = $event->getPage();

        $ext = $page->getExtension(self::EXTENSION_NAME);
        if (!$ext instanceof ArrayEntity) {
            $ext = new ArrayEntity();
            $page->addExtension(self::EXTENSION_NAME, $ext);
        }

        $providers = $ext->get('providers');
        if (!is_array($providers)) {
            $providers = [];
        }

        $salesChannelContext = $event->getSalesChannelContext();

        try {
            $cardsPayload = $this->cardService->getSavedCards($salesChannelContext);
        } catch (\Throwable) {
            return;
        }

        $cards = $cardsPayload['cards'] ?? [];
        if (!is_array($cards)) {
            $cards = [];
        }

        $saved = [];
        foreach ($cards as $card) {
            if (!is_array($card)) {
                continue;
            }

            $id = $card['id'] ?? null;
            if (!$id) {
                continue;
            }

            $last4 = $card['last4'] ?? null;
            $brand = $card['cardBrand'] ?? $card['brand'] ?? null;
            $expMonth = $card['expMonth'] ?? $card['exp_month'] ?? null;
            $expYear = $card['expYear'] ?? $card['exp_year'] ?? null;

            $labelParts = [];
            if ($brand) {
                $labelParts[] = (string) $brand;
            }
            if ($last4) {
                $labelParts[] = '•••• ' . (string) $last4;
            }
            if ($expMonth && $expYear) {
                $labelParts[] = sprintf('(%s/%s)', $expMonth, $expYear);
            }

            $label = trim(implode(' ', $labelParts));
            if ($label === '') {
                $label = (string) $id;
            }

            $saved[] = [
                'id' => (string) $id,
                'label' => $label,
            ];
        }

        $providerPayload = [
            'providerCode' => 'square',
            'supportsSavedPaymentMethods' => true,
            'savedPaymentMethods' => $saved,
            'addCardUrl' => '/account/squarepayments/saved-cards',
            'saveChoiceUrl' => '/account/squarepayments/subscription-card-choice',
            'savedPaymentMethodIdFieldName' => 'cardId',
        ];

        $squareHandlers = [
            'SquarePayments\\Gateways\\CreditCard',
        ];

        foreach ($squareHandlers as $handler) {
            if (!isset($providers[$handler])) {
                $providers[$handler] = $providerPayload;
            }
        }

        $ext->set('providers', $providers);
    }
}
