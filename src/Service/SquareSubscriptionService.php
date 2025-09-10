<?php

namespace SquarePayments\Service;

use Square\SquareClient;
use Square\Exceptions\ApiException;
use Square\Models\CreateSubscriptionRequest;
use Square\Models\SwapPlanRequest;
use SquarePayments\Service\SquareApiFactory;

class SquareSubscriptionService
{
    private SquareClient $client;

    public function __construct(SquareApiFactory $client)
    {
        $this->client = $client->create();
    }

    public function createSubscription(array $data)
    {
        $request = new CreateSubscriptionRequest(...$data);
        try {
            $response = $this->client->getSubscriptionsApi()->createSubscription($request);
            return $response->getResult();
        } catch (ApiException $e) {
            return $e->getMessage();
        }
    }

    public function cancelSubscription(string $subscriptionId)
    {
        $request = new CancelSubscriptionsRequest();
        try {
            $response = $this->client->getSubscriptionsApi()->cancelSubscription($subscriptionId, $request);
            return $response->getResult();
        } catch (ApiException $e) {
            return $e->getMessage();
        }
    }

    public function swapPlan(string $subscriptionId, array $data)
    {
        $request = new SwapPlanRequest(...$data);
        try {
            $response = $this->client->getSubscriptionsApi()->swapPlan($subscriptionId, $request);
            return $response->getResult();
        } catch (ApiException $e) {
            return $e->getMessage();
        }
    }
}

