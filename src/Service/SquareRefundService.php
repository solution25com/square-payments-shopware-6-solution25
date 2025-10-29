<?php

namespace SquarePayments\Service;

use Square\SquareClient;
use Square\Exceptions\ApiException;
use Square\Models\RefundPaymentRequest;
use SquarePayments\Service\SquareApiFactory;

class SquareRefundService
{
    private SquareClient $client;
    private ResponseHandleService $responseHandleService;

    public function __construct(SquareApiFactory $client, ResponseHandleService $responseHandleService)
    {
        $this->client = $client->create();
        $this->responseHandleService = $responseHandleService;
    }
    /**
     * @param RefundPaymentRequest $data
     * @return array<string,mixed>
     */
    public function refundPayment(RefundPaymentRequest $data): array
    {
        try {
            $response = $this->client->getRefundsApi()->refundPayment($data);
            $result = $response->getResult();
            return $this->responseHandleService->process($result);
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
