<?php
namespace SquarePayments\Service;

use Square\SquareClient;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SquareApiTestService
{
    private LoggerInterface $logger;
    private SystemConfigService $systemConfigService;

    public function __construct(LoggerInterface $logger, SystemConfigService $systemConfigService)
    {
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
    }

    public function checkLocation(array $payload): array
    {
        try {
            $environment = $payload['environment'] ?? 'sandbox';
            $salesChannelId = $payload['salesChannelId'] ?? null;
            $accessToken = $this->getConfigValue('accessToken', $environment, $salesChannelId);
            $locationId = $this->getConfigValue('locationId', $environment, $salesChannelId);
            if (!$accessToken || !$locationId) {
                return [
                    'success' => false,
                    'message' => 'Missing access token or location ID for environment: ' . $environment
                ];
            }
            $client = new SquareClient([
                'accessToken' => $accessToken,
                'environment' => $environment,
            ]);
            $apiResponse = $client->getLocationsApi()->retrieveLocation($locationId);
            if ($apiResponse->isSuccess()) {
                return [
                    'success' => true,
                    'message' => 'Connection successful.',
                    'location' => $apiResponse->getResult()->getLocation(),
                ];
            } else {
                $error = $apiResponse->getErrors()[0] ?? null;
                return [
                    'success' => false,
                    'message' => $error ? $error->getDetail() : 'Unknown error',
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Square API Test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function getConfigValue(string $key, string $environment, ?string $salesChannelId): ?string
    {
        $envSuffix = $environment === 'production' ? 'Production' : 'Sandbox';
        $configKey = "SquarePayments.config.{$key}{$envSuffix}";
        return $this->systemConfigService->get($configKey, $salesChannelId);
    }
}
