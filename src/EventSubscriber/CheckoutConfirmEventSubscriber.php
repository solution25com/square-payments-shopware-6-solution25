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

class CheckoutConfirmEventSubscriber implements EventSubscriberInterface
{
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


        $templateVariables = new ArrayStruct([
            'productionJS' => EnvironmentUrl::SQUARE_JS_LIVE->value,
            'sandboxJS' => EnvironmentUrl::SQUARE_JS_SANDBOX->value,
            'template' => '@SquarePayments/storefront/component/payment/credit-card.html.twig',
            'isGuestLogin' => $isGuestLogin,
        ]);

        $pageObject->addExtension(
            'squarepayments',
            $templateVariables
        );
    }
}
