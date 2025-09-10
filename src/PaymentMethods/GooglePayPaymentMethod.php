<?php

namespace SquarePayments\PaymentMethods;

use SquarePayments\Gateways\GooglePay;
use SquarePayments\PaymentMethods\PaymentMethodInterface;

class GooglePayPaymentMethod implements PaymentMethodInterface
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'Square Payments Google Pay';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Square Payments Payment';
    }

    /**
     * @inheritDoc
     */
    public function getPaymentHandler(): string
    {
        return GooglePay::class;
    }
}
