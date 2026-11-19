<?php

declare(strict_types=1);

// Router for the PHP built-in server: serve the page, its assets and the
// API routes below, and nothing else (no .env, composer files or vendor/).
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Serve index.html for the root path
if ($path === '/' || $path === '/index.html') {
    return false;
}

// Serve the page's static assets
$isAsset = preg_match('#^/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_.-]+\.(?:html|css|js|png|jpe?g|gif|svg|ico|webp|woff2?)$#', $path);
$isHidden = preg_match('#(^|/)(\.|vendor/|node_modules/)#', $path);
if ($isAsset && !$isHidden && is_file(__DIR__ . $path)) {
    return false;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = strtok($_SERVER['REQUEST_URI'], '?');
$uri = rtrim($uri, '/');

// GET /config
if ($method === 'GET' && $uri === '/config') {
    require __DIR__ . '/api/config.php';
    exit;
}

// GET /disputes
if ($method === 'GET' && $uri === '/disputes') {
    require __DIR__ . '/api/list-disputes.php';
    exit;
}

// Routes with a dispute ID: /disputes/{id}[/action]
if (preg_match('#^/disputes/([^/]+)(/accept|/challenge)?$#', $uri, $m)) {
    $disputeId = urldecode($m[1]);
    $action = $m[2] ?? '';

    if ($method === 'GET' && $action === '') {
        require __DIR__ . '/api/dispute-detail.php';
        exit;
    }

    if ($method === 'POST' && $action === '/accept') {
        require __DIR__ . '/api/accept-dispute.php';
        exit;
    }

    if ($method === 'POST' && $action === '/challenge') {
        require __DIR__ . '/api/challenge-dispute.php';
        exit;
    }
}

http_response_code(404);
echo json_encode(['error' => ['code' => '404', 'message' => 'Not found']]);
