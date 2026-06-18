<?php

declare(strict_types=1);

require_once __DIR__ . '/../sdk-config.php';

use GlobalPayments\Api\Services\ReportingService;

try {
    configureGpApi();

    // $disputeId is set by router.php
    $response = ReportingService::disputeDetail($disputeId)->execute();

    echo json_encode([
        'dispute' => [
            'caseId'                     => $response->caseId,
            'caseStatus'                 => $response->caseStatus,
            'caseStage'                  => $response->caseStage,
            'caseAmount'                 => $response->caseAmount,
            'caseCurrency'               => normalizeCurrency($response->caseCurrency),
            'transactionCardType'        => $response->transactionCardType,
            'transactionMaskedCardNumber' => $response->transactionMaskedCardNumber,
            'transactionARN'             => $response->transactionARN,
            'respondByDate'              => $response->respondByDate?->format(DateTime::ATOM),
            'depositDate'                => !empty($response->depositDate)
                ? (new DateTime($response->depositDate))->format(DateTime::ATOM)
                : null,
            'caseIdTime'                 => $response->caseIdTime?->format(DateTime::ATOM),
            'merchantHierarchy'          => $response->merchantHierarchy,
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => ['code' => $e->getCode() ?: '500', 'message' => $e->getMessage()]]);
}
