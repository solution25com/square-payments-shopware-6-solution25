<?php

declare(strict_types=1);

namespace SquarePayments\Service;

use Square\Environment;
use Square\SquareClient;

final class SquareApiFactory
{
    public function __construct(
        private readonly SquareConfigService $squareConfigService
    ) {
    }

    public function create(): SquareClient
    {
        $mode = $this->squareConfigService->get('mode') ?? 'sandbox';
        $isSandbox = $mode === 'sandbox';

        $accessToken = $isSandbox
            ? $this->squareConfigService->get('accessTokenSandbox')
            : $this->squareConfigService->get('accessTokenProduction');

        $environment = $isSandbox ? Environment::SANDBOX : Environment::PRODUCTION;

        return new SquareClient([
            'accessToken' => $accessToken,
            'environment' => $environment,
        ]);
    }
    public function createClientByEnv(string $environment): SquareClient
    {
        $isSandbox = $environment == Environment::SANDBOX;

        $accessToken = $isSandbox
            ? $this->squareConfigService->get('accessTokenSandbox')
            : $this->squareConfigService->get('accessTokenProduction');

        return new SquareClient([
            'accessToken' => $accessToken,
            'environment' => $environment,
        ]);
    }

    public function getLocationId(): string
    {
        $mode = $this->squareConfigService->get('mode') ?? 'sandbox';
        $isSandbox = $mode === 'sandbox';

        return $isSandbox
            ? $this->squareConfigService->get('locationIdSandbox')
            : $this->squareConfigService->get('locationIdProduction');
    }

    public function getApplicationId(): string
    {
        $mode = $this->squareConfigService->get('mode') ?? 'sandbox';
        $isSandbox = $mode === 'sandbox';

        return $isSandbox
            ? $this->squareConfigService->get('applicationIdSandbox')
            : $this->squareConfigService->get('applicationIdProduction');
    }
}
