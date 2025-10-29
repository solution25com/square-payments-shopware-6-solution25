<?php

declare(strict_types=1);

namespace SquarePayments\Service;

class ResponseHandleService
{
    public function __construct()
    {
    }
    /**
     * @param mixed $result
     * @return array<string,mixed>
     */
    public function process(mixed $result): array
    {
        return $this->handleResult($result);
    }

    /**
     * @param mixed $result
     * @return array<string,mixed>
     */
    private function handleResult(mixed $result): array
    {
        // Handle both SDK object response and potential array response
        if (is_array($result)) {
            if (!empty($result['errors'])) {
                $firstError = $result['errors'][0] ?? null;
                $message = is_array($firstError)
                    ? ($firstError['detail'] ?? ($firstError['category'] ?? 'Payment creation failed'))
                    : 'Payment creation failed';
                return ['status' => 'error', 'message' => $message];
            }

            $paymentArray = $result['payment'] ?? null;

            return ['status' => 'success', 'payment' => $paymentArray];
        }

        // Object response path
        if (is_object($result) && method_exists($result, 'getErrors') && $result->getErrors()) {
            $firstError = $result->getErrors()[0] ?? null;
            $message = $firstError ? ($firstError->getDetail() ?? $firstError->getCategory() ?? 'Payment creation failed') : 'Payment creation failed';
            return ['status' => 'error', 'message' => $message];
        }

        $payment = (is_object($result) && method_exists($result, 'getPayment')) ? $result->getPayment() : null;
        if (!$payment) {
            $payment = (is_object($result) && method_exists($result, 'getRefund')) ? $result->getRefund() : null;
        }
        $paymentArray = $payment ? json_decode((string)json_encode($payment), true) : null;

        return ['status' => 'success', 'payment' => $paymentArray];
    }
}
