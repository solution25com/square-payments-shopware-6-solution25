<?php

namespace SquarePayments\PaymentMethods;

use SquarePayments\Gateways\ApplePay;
use SquarePayments\PaymentMethods\PaymentMethodInterface;

class ApplePayPaymentMethod implements PaymentMethodInterface
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'Square Payments Apple Pay';
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
        return ApplePay::class;
    }
}
