<?php

declare(strict_types=1);

namespace SquarePayments\Library;

enum TransactionStatuses: string
{
    case AUTHORIZED = 'authorized';
    case PENDING = 'pending';
    case PAID    = 'paid';
    case FAIL    = "fail";
    case REFUND  = "refund";
    case VOIDED  = "voided";
}
