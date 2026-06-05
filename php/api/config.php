<?php

declare(strict_types=1);

require_once __DIR__ . '/../sdk-config.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

echo json_encode([
    'environment' => $_ENV['GP_ENVIRONMENT'] ?? 'sandbox',
    'backend' => 'php',
]);
