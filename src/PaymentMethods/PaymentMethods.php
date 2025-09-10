<?php

namespace SquarePayments\PaymentMethods;

class PaymentMethods
{
    public const PAYMENT_METHODS = [
      CreditCardPaymentMethod::class,
      GooglePayPaymentMethod::class,
      ApplePayPaymentMethod::class,
    ];
}
