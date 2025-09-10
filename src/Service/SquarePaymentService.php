<?php

namespace SquarePayments\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Square\Models\Address;
use Square\Models\Money;
use Square\SquareClient;
use Square\Models\CreatePaymentRequest;
use Square\Models\CompletePaymentRequest;
use Symfony\Component\HttpFoundation\Request;

class SquarePaymentService
{
    private SquareClient $client;
    private ResponseHandleService $responseHandleService;
    private SquareCardService $cardService;
    private SquareApiFactory $squareApiFactory;
    private SquareConfigService $squareConfigService;

    private LoggerInterface $logger;

    public function __construct(
        SquareConfigService $squareConfigService,
        SquareApiFactory $client,
        ResponseHandleService $responseHandleService,
        SquareCardService $cardService,
        LoggerInterface $logger
    ) {
        $this->squareConfigService = $squareConfigService;
        $this->squareApiFactory = $client;
        $this->client = $client->create();
        $this->responseHandleService = $responseHandleService;
        $this->cardService = $cardService;
        $this->logger = $logger;
    }

    public function capturePayment(string $paymentId, ?CompletePaymentRequest $body = null)
    {
        try {
            $response = $this->client->getPaymentsApi()->completePayment(
                $paymentId,
                $body ?? new CompletePaymentRequest()
            );
            $result = $response->getResult();
            return $this->responseHandleService->process($result);
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function voidPayment(string $paymentId)
    {
        try {
            $response = $this->client->getPaymentsApi()->cancelPayment($paymentId);
            $result = $response->getResult();
            return $this->responseHandleService->process($result);
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function authorizePayment(Request $request, SalesChannelContext $context)
    {
        $this->logger->debug('start of authorizePayment');
        $this->logger->debug('authorizepayment request', [$request->request->all()]);
        $data = $this->parsePayload($request);
        $this->logger->debug("beginning payload", ['payload' => $data]);

        $sourceId = $data['paymentToken'] ?? $data['cardId'] ?? null;

        if (!$sourceId) {
            return ['status' => 'error', 'message' => 'Source ID is required'];
        }

        $customer = $context->getCustomer();
        $idempotencyKey = $this->generateIdempotencyKey();
        $squareCustomerId = null;
        if ($customer) {
            $squareCustomerId = $this->cardService->getOrCreateSquareCustomerId(
                $customer->getId(),
                $data,
                $context->getContext()
            );
        }
        $currency = strtoupper(
            $data['currency'] ?? ($context->getCurrency() ? $context->getCurrency()->getIsoCode() : 'USD')
        );

        if (isset($data['minorAmount']) && ($data['minorAmount'] === true || $data['minorAmount'] === 1 || $data['minorAmount'] === '1')) {
            $amountMinor = (int)($data['amount'] ?? 0);
        } else {
            $amountMinor = $this->toMinorUnit($data['amount'] ?? null, $currency);
        }

        $money = new Money();
        $money->setAmount($amountMinor);
        $money->setCurrency($currency);

        $paymentRequest = new CreatePaymentRequest($sourceId, $idempotencyKey);
        $paymentRequest->setCustomerId($squareCustomerId);
        $paymentRequest->setAmountMoney($money);
        $paymentRequest->setLocationId($this->squareApiFactory->getLocationId());
        $paymentMode = $this->squareConfigService->get('paymentMode');
        $paymentRequest->setAutocomplete($paymentMode === 'AUTHORIZE_AND_CAPTURE');

        try {
            $response = $this->client->getPaymentsApi()->createPayment($paymentRequest);
            $result = $response->getResult();
            $processed = $this->responseHandleService->process($result);
            $this->logger->debug("completed payload", ['processed' => $processed]);

            if ($processed['status'] === 'success' && isset($data['saveCard'])) {
                $customerName = trim(($customer?->getFirstName() ?? '') . ' ' . ($customer?->getLastName() ?? ''));
                $payload = [
                    'sourceId' => $sourceId,
                    'paymentId' => $processed['payment']['id'] ?? '',
                    'cardholderName' => $customerName
                ];
                $addCardResult = $this->cardService->addCard(new Request([], $payload), $context);
                $this->logger->debug("Add Card result", ['result' => $addCardResult]);
            }
            return $processed;
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

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

    private function toMinorUnit($amount, string $currency): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        if (\is_string($amount)) {
            $amount = str_replace([','], ['.'], $amount);
        }

        $value = (float)$amount;
        $zeroDecimalCurrencies = ['JPY', 'KRW', 'VND', 'CLP', 'UGX', 'XAF', 'XOF', 'KMF'];
        $isZeroDecimal = \in_array(strtoupper($currency), $zeroDecimalCurrencies, true);

        return $isZeroDecimal ? (int)round($value) : (int)round($value * 100);
    }

    private function buildBillingAddress(array $data, SalesChannelContext $context): ?Address
    {
        $customer = $context->getCustomer();
        $billing = $customer->getActiveBillingAddress();
        $addrData = $data['billingAddress'] ?? [];
        $addr = new Address();
        $firstName = $addrData['givenName'] ?? $billing?->getFirstName() ?? '';
        $lastName = $addrData['familyName'] ?? $billing?->getLastName() ?? '';
        $line1 = $addrData['addressLines'][0]
            ?? $billing?->getStreet()
            ?? '';
        $line2 = $addrData['addressLines'][1]
            ?? $billing?->getAdditionalAddressLine1()
            ?? '';
        $line3 = $addrData['addressLines'][2]
            ?? $billing?->getAdditionalAddressLine2()
            ?? '';
        $city = $addrData['locality'] ?? $billing?->getCity() ?? '';
        $country = strtoupper($addrData['countryCode'] ?? $billing?->getCountry()?->getIso() ?? '');
        $postalCode = $addrData['postalCode'] ?? $billing?->getZipcode() ?? '';

        $addr->setFirstName($firstName);
        $addr->setLastName($lastName);
        $addr->setAddressLine1($line1);
        $addr->setAddressLine2($line2);
        $addr->setAddressLine3($line3);
        $addr->setLocality($city);
        $addr->setCountry($country);
        $addr->setPostalCode($postalCode);
        return $addr;
    }

    function generateIdempotencyKey(): string
    {
        $data = random_bytes(16);

        // Set the version to 0100 (v4)
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // Set bits 6–7 to 10 (variant)
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }


    public
    function isSubscriptionOrder(
        OrderEntity $order
    ): bool {
        $isSubscription = $order->getExtensionOfType('foreignKeys', ArrayStruct::class)?->get(
                'subscriptionId'
            ) !== null || $order->getExtensionOfType('foreignKeys', ArrayStruct::class)?->get(
                's25SubscriptionId'
            ) !== null;

        return $isSubscription;
    }
}

