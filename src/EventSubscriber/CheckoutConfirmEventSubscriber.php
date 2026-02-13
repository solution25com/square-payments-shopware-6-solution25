<?php

declare(strict_types=1);

namespace SquarePayments\EventSubscriber;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Storefront\Page\PageLoadedEvent;
use SquarePayments\Gateways\CreditCard;
use SquarePayments\Library\EnvironmentUrl;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use SquarePayments\Service\SquareConfigService;

class CheckoutConfirmEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly SquareConfigService $squareConfigService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'addPaymentMethodSpecificFormFields',
            AccountEditOrderPageLoadedEvent::class => 'addPaymentMethodSpecificFormFields'
        ];
    }

    public function addPaymentMethodSpecificFormFields(PageLoadedEvent $event): void
    {
        $pageObject = $event->getPage();
        $salesChannelContext = $event->getSalesChannelContext();
        $selectedPaymentGateway = $salesChannelContext->getPaymentMethod();

        if ($selectedPaymentGateway->getHandlerIdentifier() !== CreditCard::class) {
            return;
        }

        $customer = $event->getSalesChannelContext()->getCustomer();
        if (!$customer instanceof CustomerEntity) {
            return;
        }

        $isGuestLogin = $customer->getGuest();

        $salesChannelId = $salesChannelContext->getSalesChannelId();
        if (!$this->squareConfigService->isConfigured($salesChannelId)) {
            return;
        }

        $amount = 0;
        if ($event instanceof CheckoutConfirmPageLoadedEvent) {
            $amount = $pageObject->getCart()->getPrice()->getTotalPrice();
        } elseif ($event instanceof AccountEditOrderPageLoadedEvent) {
            $amount = $pageObject->getOrder()->getAmountTotal();
        }

        $templateVariables = new ArrayStruct([
            'productionJS' => EnvironmentUrl::SQUARE_JS_LIVE->value,
            'sandboxJS' => EnvironmentUrl::SQUARE_JS_SANDBOX->value,
            'template' => '@SquarePayments/storefront/component/payment/credit-card.html.twig',
            'isGuestLogin' => $isGuestLogin,
            'amount' => $amount,
        ]);

        $pageObject->addExtension(
            'squarepayments',
            $templateVariables
        );
    }
}
