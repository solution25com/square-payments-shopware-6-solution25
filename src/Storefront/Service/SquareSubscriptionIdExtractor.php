<?php

declare(strict_types=1);

namespace SquarePayments\Storefront\Service;

final class SquareSubscriptionIdExtractor
{
    public function extract(object $order): ?string
    {
        if (method_exists($order, 'getSubscriptionId')) {
            $value = $order->getSubscriptionId();
            if (\is_string($value) && $value !== '') {
                return $value;
            }
        }

        if (method_exists($order, 'getS25SubscriptionId')) {
            $value = $order->getS25SubscriptionId();
            return \is_string($value) ? $value : null;
        }

        if (method_exists($order, 'get')) {
            $value = $this->getFieldValueSafely($order, 'subscriptionId');
            if (\is_string($value) && $value !== '') {
                return $value;
            }

            $value = $this->getFieldValueSafely($order, 's25SubscriptionId');
            if (\is_string($value)) {
                return $value;
            }
        }

        if (method_exists($order, 'hasExtension') && $order->hasExtension('subscription')
            && method_exists($order, 'getExtension')) {
            $subscription = $order->getExtension('subscription');
            if (\is_object($subscription) && method_exists($subscription, 'getId')) {
                $value = $subscription->getId();
                if (\is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        if ($order instanceof \ArrayAccess && isset($order['subscriptionId']) && \is_string($order['subscriptionId'])) {
            return $order['subscriptionId'];
        }

        if ($order instanceof \ArrayAccess && isset($order['s25SubscriptionId']) && \is_string($order['s25SubscriptionId'])) {
            return $order['s25SubscriptionId'];
        }

        if (method_exists($order, 'getCustomFields')) {
            $customFields = $order->getCustomFields() ?? [];
            $value = $customFields['subscriptionId'] ?? $customFields['subscription_id'] ?? null;
            if (\is_string($value) && $value !== '') {
                return $value;
            }

            $value = $customFields['s25SubscriptionId'] ?? $customFields['s25_subscription_id'] ?? null;
            return \is_string($value) ? $value : null;
        }

        return null;
    }

    private function getFieldValueSafely(object $order, string $field): mixed
    {
        try {
            return $order->get($field);
        } catch (\Throwable) {
            return null;
        }
    }
}
