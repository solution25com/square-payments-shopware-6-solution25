<?php

namespace SquarePayments\Service;

use Square\Environment;
use Square\SquareClient;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SquareApiTestService
{
    private LoggerInterface $logger;
    private SystemConfigService $systemConfigService;

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $systemConfigService
    ) {
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function checkLocation(array $payload): array
    {
        try {
            $configEnv = ($payload['environment'] ?? 'sandbox') === 'production'
                ? 'production'
                : 'sandbox';

            $sdkEnv = $configEnv === 'production'
                ? Environment::PRODUCTION
                : Environment::SANDBOX;

            $salesChannelId = $payload['salesChannelId'] ?? null;

            $accessToken = $this->getConfigValue('accessToken', $configEnv, $salesChannelId);
            $locationId  = $this->getConfigValue('locationId', $configEnv, $salesChannelId);

            if (!$accessToken || !$locationId) {
                return [
                    'success' => false,
                    'message' => 'Missing access token or location ID for environment: ' . $configEnv,
                ];
            }

            $client = new SquareClient([
                'accessToken' => $accessToken,
                'environment' => $sdkEnv,
            ]);

            $apiResponse = $client
                ->getLocationsApi()
                ->retrieveLocation($locationId);

            if ($apiResponse->isSuccess()) {
                return [
                    'success' => true,
                    'message' => 'Connection successful.',
                    'location' => $apiResponse->getResult()->getLocation(),
                ];
            }

            $error = $apiResponse->getErrors()[0] ?? null;

            return [
                'success' => false,
                'message' => $error ? $error->getDetail() : 'Unknown error',
            ];
        } catch (\Throwable $e) {
            $this->logger->error(
                'Square API Test failed: ' . $e->getMessage(),
                ['exception' => $e]
            );

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function getConfigValue(
        string $key,
        string $environment,
        ?string $salesChannelId
    ): ?string {
        $envSuffix = $environment === 'production' ? 'Production' : 'Sandbox';
        $configKey = "SquarePayments.config.{$key}{$envSuffix}";
        $value = $this->systemConfigService->get($configKey, $salesChannelId);

        return is_scalar($value) ? (string) $value : null;
    }
}
