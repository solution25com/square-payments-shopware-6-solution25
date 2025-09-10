<?php

declare(strict_types=1);

namespace SquarePayments\Library;

abstract class Endpoints
{
    protected const PAYMENTS = 'PAYMENTS';
    protected const REFUNDS = 'REFUNDS';
    protected const CUSTOMERS = 'CUSTOMERS';
    protected const ORDERS = 'ORDERS';
    protected const CHECKOUT = 'CHECKOUT';
    protected const CARDS = 'CARDS';
    protected const LOCATIONS = 'LOCATIONS';

    private static array $endpoints = [
        self::PAYMENTS => [
            'method' => 'POST',
            'url' => '/v2/payments'
        ],
        self::REFUNDS => [
            'method' => 'POST',
            'url' => '/v2/refunds'
        ],
        self::CUSTOMERS => [
            'method' => 'POST',
            'url' => '/v2/customers'
        ],
        self::ORDERS => [
            'method' => 'POST',
            'url' => '/v2/orders'
        ],
        self::CHECKOUT => [
            'method' => 'POST',
            'url' => '/v2/checkout'
        ],
        self::CARDS => [
            'method' => 'POST',
            'url' => '/v2/cards'
        ],
        self::LOCATIONS => [
            'method' => 'GET',
            'url' => '/v2/locations'
        ]
    ];

    protected static function getEndpoint(string $endpoint): array
    {
        return self::$endpoints[$endpoint];
    }

    public static function getUrl(string $endpoint, ?string $id = null): array
    {
        $endpointDetails = self::getEndpoint($endpoint);
        $baseUrl = $endpointDetails['url'];
        return [
            'method' => $endpointDetails['method'],
            'url' => $id ? $baseUrl . '/' . $id : $baseUrl
        ];
    }

    public static function getUrlDynamicParam(string $endpoint, ?array $params = [], ?array $queryParam = []): array
    {
        $endpointDetails = self::getEndpoint($endpoint);
        $baseUrl = $endpointDetails['url'];
        $paramBuilder = '';
        foreach ($params as $value) {
            $paramBuilder .= '/' . $value;
        }

        if (!empty($queryParam)) {
            $queryString = '?' . http_build_query($queryParam);
        } else {
            $queryString = '';
        }

        return [
            'method' => $endpointDetails['method'],
            'url' => $baseUrl . $paramBuilder . $queryString
        ];
    }
}
