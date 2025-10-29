<?php

namespace SquarePayments\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Square\Models\Address;
use Square\Models\Address as SqAddress;
use Square\Models\Card;
use Square\Models\CreateCardRequest;
use Square\Models\CreateCustomerRequest;
use Square\SquareClient;
use Square\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Checkout\Customer\CustomerCollection;

class SquareCardService
{
    private SquareClient $client;

    /** @param EntityRepository<CustomerCollection> $customerRepository */
    public function __construct(
        SquareApiFactory $client,
        private readonly SquareCustomerService $customerService,
        private readonly EntityRepository $customerRepository,
        private readonly LoggerInterface $logger
    ) {
        $this->client = $client->create();
    }

    /** @return array<string,mixed> */
    public function getSavedCards(SalesChannelContext $context): array
    {
        try {
            $customerId = $context->getCustomer()?->getId();
            if (!$customerId) {
                return ['cards' => []];
            }
            $squareCustomerId = $this->getSquareCustomerId($customerId, $context->getContext());
            if (!$squareCustomerId) {
                return ['cards' => []];
            }
            $response = $this->client->getCardsApi()->listCards(null, $squareCustomerId);
            $result = $response->getResult();
            $this->logger->debug('Get saved cards', ['request' => $result]);


            // Array response: normalize to only the 'cards' key
            if (is_array($result)) {
                return ['cards' => $result['cards'] ?? []];
            }

            // Object response: prefer getter, then jsonSerialize
            if (is_object($result)) {
                if (method_exists($result, 'getCards')) {
                    $cards = $result->getCards();
                    // Convert possible SDK objects to arrays
                    $cardsArray = array_map(static function ($c) {
                        if (is_object($c) && method_exists($c, 'jsonSerialize')) {
                            $serialized = $c->jsonSerialize();
                            return is_array($serialized) ? $serialized : json_decode((string)json_encode($serialized), true);
                        }
                        return is_array($c) ? $c : json_decode((string)json_encode($c), true);
                    }, $cards ?? []);

                    return ['cards' => $cardsArray];
                }

                if (method_exists($result, 'jsonSerialize')) {
                    $serialized = $result->jsonSerialize();
                    $data = is_array($serialized) ? $serialized : json_decode((string)json_encode($serialized), true);

                    return ['cards' => $data['cards'] ?? []];
                }
            }
        } catch (ApiException $e) {
            //todo log the error the file
        }
        return ['cards' => []];
    }

    /** @return array<string,mixed> */
    public function getSavedCard(SalesChannelContext $context, string $cardId): array
    {
        try {
            $customerId = $context->getCustomer()?->getId();
            if (!$customerId) {
                return ['card' => null];
            }
            $squareCustomerId = $this->getSquareCustomerId($customerId, $context->getContext());
            if (!$squareCustomerId) {
                return ['card' => null];
            }
            $response = $this->client->getCardsApi()->retrieveCard($cardId);
            $result = $response->getResult();
            $this->logger->debug('Get saved card', ['request' => $result]);

            if (is_object($result)) {
                if (method_exists($result, 'getCard')) {
                    $card = $result->getCard();
                    if (is_object($card) && method_exists($card, 'jsonSerialize')) {
                        $serialized = $card->jsonSerialize();
                        return ['card' => is_array($serialized) ? $serialized : json_decode((string)json_encode($serialized), true)];
                    }
                    return ['card' => is_array($card) ? $card : json_decode((string)json_encode($card), true)];
                }

                if (method_exists($result, 'jsonSerialize')) {
                    $serialized = $result->jsonSerialize();
                    $data = is_array($serialized) ? $serialized : json_decode((string)json_encode($serialized), true);

                    return ['card' => $data['card'] ?? null];
                }
            }
        } catch (Exception $e) {
        }
        return ['card' => null];
    }

    /** @return array<string,mixed> */
    public function addCard(Request $request, SalesChannelContext $context): array
    {
        $customer = $context->getCustomer();
        if (!$customer) {
            $this->logger->debug("Customer not found for card creation");
            return ['error' => 'No customer'];
        }

        $payload = $this->parsePayload($request);

        $sourceId =  $payload['paymentId'] ?? $payload['cardToken'] ?? null;
        if (!$sourceId) {
            $this->logger->debug("Source id not found for card creation");

            return ['error' => 'Missing card token'];
        }

        $squareCustomerId = $this->getOrCreateSquareCustomerId($customer->getId(), $payload, $context->getContext());
        if (!$squareCustomerId) {
            $this->logger->debug("square customer id could be not found or created for card creation");
            return ['error' => 'Could not create Square customer'];
        }
        $idempotencyKey = $this->generateIdempotencyKey();
        $card = new Card();
        if (isset($payload['cardholderName'])) {
            $card->setCardholderName($payload['cardholderName']);
        } elseif (isset($payload['billingAddress'])) {
            $billingAddress = $payload['billingAddress'];
            $customerName = trim(($billingAddress['firstName'] ?? '') . ' ' . ($billingAddress['lastName'] ?? ''));
            $card->setCardholderName($customerName);
        }
        $card->setCustomerId($squareCustomerId);
        if (isset($payload['billingAddress'], $payload['cardToken'])) {
            $address = $this->getAddress($payload['billingAddress']);
            $card->setBillingAddress($address);
        }
        $req = new CreateCardRequest($idempotencyKey, $sourceId, $card);
        try {
            $response = $this->client->getCardsApi()->createCard($req);
            $result = $response->getResult();
            // Return raw json-serialized response
            if (\is_object($result) && method_exists($result, 'jsonSerialize')) {
                $serialized = $result->jsonSerialize();
                return \is_array($serialized) ? $serialized : json_decode((string)json_encode($serialized), true);
            }
            return \is_array($result) ? $result : json_decode((string)json_encode($result), true);
        } catch (ApiException $e) {
            $this->logger->debug('Card creation error', ['message' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /** @return array<string,mixed> */
    public function deleteCard(string $cardId, SalesChannelContext $context): array
    {
        try {
            $response = $this->client->getCardsApi()->disableCard($cardId);
            return (array)$response->getResult()->jsonSerialize();
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /** @param array<string,mixed> $payload */
    public function getOrCreateSquareCustomerId(string $shopwareCustomerId, array $payload, Context $context): ?string
    {
        // Load customer to get current custom fields
        $criteria = new Criteria([$shopwareCustomerId]);
        $criteria->addAssociation('customFields');
        $customer = $this->customerRepository->search($criteria, $context)->first();
        $customFields = ($customer instanceof CustomerEntity ? $customer->getCustomFields() : []) ?? [];
        $squareId = $customFields['squarepayments_square_customer_id'] ?? null;
        if ($squareId) {
            $this->logger->debug('Found square customer id in custom fieelds', ['squareid' => $squareId]);
            return (string)$squareId;
        }
        $request = new CreateCustomerRequest();
        $this->logger->debug('Creating customer', ['payload' => $payload]);


        // Create Square customer
        if (isset($payload['billingAddress'])) {
            $address = $this->getAddress($payload['billingAddress']);
            $request->setAddress($address);
            $request->setGivenName($payload['billingAddress']['givenName'] ?? null);
            $request->setFamilyName($payload['billingAddress']['familyName'] ?? null);
            $request->setEmailAddress($payload['billingAddress']['email'] ?? null);
            $request->setPhoneNumber($payload['billingAddress']['phone'] ?? null);
        }


        $createResult = $this->customerService->createCustomer($request);
        $this->logger->debug('Create customer request', ['request' => $request, 'result' => $createResult]);
        // Handle both array/object results
        $id = null;
        if (\is_object($createResult) && method_exists($createResult, 'getCustomer') && $createResult->getCustomer()) {
            $id = $createResult->getCustomer()->getId();
        } elseif (\is_array($createResult)) {
            $id = $createResult['customer']['id'] ?? null;
        }

        if (!$id) {
            return null;
        }

        // Persist mapping into customer custom fields
        $this->customerRepository->update([
            [
                'id' => $shopwareCustomerId,
                'customFields' => array_merge($customFields, ['squarepayments_square_customer_id' => $id]),
            ]
        ], $context);

        return $id;
    }

    /** @return string|null */
    public function getSquareCustomerId(string $shopwareCustomerId, Context $context): ?string
    {
        $criteria = new Criteria([$shopwareCustomerId]);
        $criteria->addAssociation('customFields');
        $customer = $this->customerRepository->search($criteria, $context)->first();
        $customFields = ($customer instanceof CustomerEntity ? $customer->getCustomFields() : []) ?? [];
        $squareId = $customFields['squarepayments_square_customer_id'] ?? null;
        if ($squareId) {
            $this->logger->debug('Found square customer id in custom fieelds', ['squareid' => $squareId]);
            return (string)$squareId;
        }
        return null;
    }

    /** @return array<string,mixed> */
    private function parsePayload(Request $request): array
    {
        $contentType = (string)$request->headers->get('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            $content = $request->getContent();
            if ($content !== '') {
                try {
                    $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                    if (\is_array($decoded)) {
                        return $decoded;
                    }
                } catch (\Throwable) {
                    // fall back to request parameters
                }
            }
        }

        return $request->request->all();
    }

    private function generateIdempotencyKey(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /** @param array<string,mixed> $billingAddress */
    public function getAddress(array $billingAddress): SqAddress
    {
        $address = new Address();
        $address->setFirstName($billingAddress['firstName'] ?? null);
        $address->setLastName($billingAddress['lastName'] ?? null);
        $address->setAddressLine1($billingAddress['addressLine1'] ?? null);
        $address->setAddressLine2($billingAddress['addressLine2'] ?? null);
        $address->setAddressLine3($billingAddress['addressLine3'] ?? null);
        $address->setCountry($billingAddress['country'] ?? null);
        $address->setLocality($billingAddress['locality'] ?? null);
        $address->setPostalCode($billingAddress['postalCode'] ?? null);
        return $address;
    }
}
