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
    // .env is optional, the variables can also come from the environment (Docker)
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();

    $config = new GpApiConfig();
    $config->appId = envValue('GP_APP_ID');
    $config->appKey = envValue('GP_APP_KEY');
    $config->channel = Channel::CardNotPresent;
    $config->environment = (strtolower(envValue('GP_ENVIRONMENT') ?? 'sandbox') === 'production')
        ? Environment::PRODUCTION
        : Environment::TEST;

    ServicesContainer::configureService($config);
}

// $_ENV stays empty when php.ini leaves E out of variables_order, so fall back to getenv()
function envValue(string $name): ?string
{
    $value = $_ENV[$name] ?? getenv($name);
    return ($value === false || $value === '') ? null : $value;
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
