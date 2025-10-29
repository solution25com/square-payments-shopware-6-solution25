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

    public function createCustomer(CreateCustomerRequest $request): mixed
    {
        try {
            $response = $this->client->getCustomersApi()->createCustomer($request);
            return $response->getResult();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateCustomer(string $customerId, array $data): mixed
    {
        $request = new UpdateCustomerRequest();
        foreach ($data as $key => $value) {
            $setter = 'set' . ucfirst($key);
            if (method_exists($request, $setter)) {
                $request->$setter($value);
            }
        }
        try {
            $response = $this->client->getCustomersApi()->updateCustomer($customerId, $request);
            return $response->getResult();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function deleteCustomer(string $customerId): mixed
    {
        try {
            $response = $this->client->getCustomersApi()->deleteCustomer($customerId);
            return $response->getResult();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
