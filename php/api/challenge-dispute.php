<?php

declare(strict_types=1);

require_once __DIR__ . '/../sdk-config.php';

use GlobalPayments\Api\Entities\DisputeDocument;
use GlobalPayments\Api\Entities\Reporting\DisputeSummary;

try {
    configureGpApi();

    if (strtolower($_ENV['GP_ENVIRONMENT'] ?? 'sandbox') !== 'production') {
        echo json_encode([
            'success'      => true,
            'responseCode' => 'SUCCESS',
            'sandbox'      => true,
            'note'         => 'Write operations are simulated in sandbox and not submitted to the card network. This action would execute against a live dispute in production.',
        ]);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $rawDocs = $body['documents'] ?? [];

    if (empty($rawDocs)) {
        http_response_code(400);
        echo json_encode(['error' => ['code' => '400', 'message' => 'At least one document is required']]);
        exit;
    }

    $documents = array_map(function (array $raw): DisputeDocument {
        $doc = new DisputeDocument();
        $doc->type = $raw['type'] ?? null;
        $doc->b64_content = $raw['b64Content'] ?? '';
        return $doc;
    }, $rawDocs);

    $dispute = new DisputeSummary();
    $dispute->caseId = $disputeId;

    $response = $dispute->challenge($documents)->execute();

    echo json_encode([
        'success'      => true,
        'responseCode' => $response->responseCode,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => [
        'code'         => $e->getCode() ?: '500',
        'message'      => $e->getMessage(),
        'responseCode' => property_exists($e, 'responseCode') ? $e->responseCode : null,
    ]]);
}
