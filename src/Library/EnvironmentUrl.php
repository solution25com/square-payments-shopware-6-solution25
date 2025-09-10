<?php

declare(strict_types=1);

namespace SquarePayments\Library;

enum EnvironmentUrl: string
{
    case SANDBOX               = "https://connect.squareupsandbox.com";
    case LIVE                  = "https://connect.squareup.com";
    case CHECKOUT_LINK_SANDBOX = "https://sandbox.squareup.com/checkout";
    case CHECKOUT_LINK_LIVE    = "https://squareup.com/checkout";
    case SQUARE_JS_SANDBOX     = 'https://sandbox.web.squarecdn.com/v1/square.js';
    case SQUARE_JS_LIVE        = 'https://web.squarecdn.com/v1/square.js';
}
