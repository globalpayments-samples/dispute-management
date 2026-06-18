<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use GlobalPayments\Api\Entities\Enums\Channel;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpApiConfig;
use GlobalPayments\Api\ServicesContainer;

function configureGpApi(): void
{
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $config = new GpApiConfig();
    $config->appId = $_ENV['GP_APP_ID'];
    $config->appKey = $_ENV['GP_APP_KEY'];
    $config->channel = Channel::CardNotPresent;
    $config->environment = (strtolower($_ENV['GP_ENVIRONMENT'] ?? 'sandbox') === 'production')
        ? Environment::PRODUCTION
        : Environment::TEST;

    ServicesContainer::configureService($config);
}

function normalizeCurrency(?string $code): ?string
{
    if ($code === null) return null;
    $map = [
        'MX' => 'MXN', 'GB' => 'GBP', 'AU' => 'AUD', 'CA' => 'CAD',
        'JP' => 'JPY', 'EU' => 'EUR', 'SG' => 'SGD', 'HK' => 'HKD',
        'NZ' => 'NZD', 'ZA' => 'ZAR', 'BR' => 'BRL', 'IN' => 'INR',
        'CN' => 'CNY', 'SE' => 'SEK', 'NO' => 'NOK', 'DK' => 'DKK',
        'CH' => 'CHF', 'AE' => 'AED',
    ];
    return $map[$code] ?? $code;
}
