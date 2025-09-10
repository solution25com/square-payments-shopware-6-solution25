<?php

namespace SquarePayments\Service;

use Square\SquareClient;
use Square\Exceptions\ApiException;
use Square\Models\CreateCustomerRequest;
use Square\Models\UpdateCustomerRequest;
use Square\Models\DeleteCustomerRequest;
use SquarePayments\Service\SquareApiFactory;

class SquareCustomerService
{
    private SquareClient $client;
    /**
     * SquareCustomerService constructor.
     *
     * @param SquareApiFactory $client
     */
    public function __construct(SquareApiFactory $client)
    {
        $this->client = $client->create();
    }

    public function createCustomer(CreateCustomerRequest $request)
    {
        try {
            $response = $this->client->getCustomersApi()->createCustomer($request);
            return $response->getResult();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function updateCustomer(string $customerId, array $data)
    {
        $request = new UpdateCustomerRequest(...$data);
        try {
            $response = $this->client->getCustomersApi()->updateCustomer($customerId, $request);
            return $response->getResult();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function deleteCustomer(string $customerId)
    {
        try {
            $response = $this->client->getCustomersApi()->deleteCustomer($customerId);
            return $response->getResult();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}

