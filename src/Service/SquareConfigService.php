<?php

namespace SquarePayments\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class SquareConfigService
{
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function get(string $configName, ?string $salesChannelId = null): mixed
    {
        return $this->systemConfigService->get('SquarePayments.config.' . trim($configName), $salesChannelId);
    }

    public function set(string $configName, ?string $salesChannelId = null): void
    {
        $this->systemConfigService->set('SquarePayments.config.' . trim($configName), $salesChannelId);
    }

    public function isConfigured(?string $salesChannelId = null): bool
    {
        $mode = (string) ($this->get('mode', $salesChannelId) ?? 'sandbox');
        $isSandbox = $mode === 'sandbox';

        $accessToken = (string) ($isSandbox
            ? ($this->get('accessTokenSandbox', $salesChannelId) ?? '')
            : ($this->get('accessTokenProduction', $salesChannelId) ?? '')
        );

        $applicationId = (string) ($isSandbox
            ? ($this->get('applicationIdSandbox', $salesChannelId) ?? '')
            : ($this->get('applicationIdProduction', $salesChannelId) ?? '')
        );

        $locationId = (string) ($isSandbox
            ? ($this->get('locationIdSandbox', $salesChannelId) ?? '')
            : ($this->get('locationIdProduction', $salesChannelId) ?? '')
        );

        return $accessToken !== '' && $applicationId !== '' && $locationId !== '';
    }
}
