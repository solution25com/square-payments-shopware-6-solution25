<?php

declare(strict_types=1);

namespace SquarePayments\Library;

enum TransactionType: string
{
    case STORE = 'STORE';
    case CHARGE = 'CHARGE';
    case CHARGE_AND_STORE = 'CHARGE_AND_STORE';
}