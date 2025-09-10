<?php

namespace SquarePayments\PaymentMethods;

use SquarePayments\Gateways\CreditCard;
use SquarePayments\PaymentMethods\PaymentMethodInterface;

class CreditCardPaymentMethod implements PaymentMethodInterface
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'Square Payments Credit Card';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Square Credit Card Payment';
    }

    /**
     * @inheritDoc
     */
    public function getPaymentHandler(): string
    {
        return CreditCard::class;
    }
}
