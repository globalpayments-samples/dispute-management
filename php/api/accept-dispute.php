<?php

declare(strict_types=1);

require_once __DIR__ . '/../sdk-config.php';

use GlobalPayments\Api\Entities\Reporting\DisputeSummary;

try {
    configureGpApi();

    $dispute = new DisputeSummary();
    $dispute->caseId = $disputeId;
    $response = $dispute->accept()->execute();

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
