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
}
