<?php

declare(strict_types=1);

namespace SquarePayments\Storefront\Service;

final class SquareSubscriptionIdExtractor
{
    public function extract(object $order): ?string
    {
        if (method_exists($order, 'getS25SubscriptionId')) {
            $value = $order->getS25SubscriptionId();
            return \is_string($value) ? $value : null;
        }

        if (method_exists($order, 'get')) {
            $value = $order->get('s25SubscriptionId');
            if (\is_string($value)) {
                return $value;
            }
        }

        if ($order instanceof \ArrayAccess && isset($order['s25SubscriptionId']) && \is_string($order['s25SubscriptionId'])) {
            return $order['s25SubscriptionId'];
        }

        if (method_exists($order, 'getCustomFields')) {
            $customFields = $order->getCustomFields() ?? [];
            $value = $customFields['s25SubscriptionId'] ?? $customFields['s25_subscription_id'] ?? null;
            return \is_string($value) ? $value : null;
        }

        return null;
    }
}
