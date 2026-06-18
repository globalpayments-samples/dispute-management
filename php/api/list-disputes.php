<?php

declare(strict_types=1);

require_once __DIR__ . '/../sdk-config.php';

use GlobalPayments\Api\Entities\Enums\DisputeSortProperty;
use GlobalPayments\Api\Entities\Enums\SortDirection;
use GlobalPayments\Api\Entities\Reporting\DataServiceCriteria;
use GlobalPayments\Api\Entities\Reporting\SearchCriteria;
use GlobalPayments\Api\Services\ReportingService;

try {
    configureGpApi();

    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(50, max(1, (int) ($_GET['pageSize'] ?? 10)));
    $status   = $_GET['status'] ?? '';
    $stage    = $_GET['stage'] ?? '';
    $startDate = $_GET['startDate'] ?? '';
    $endDate   = $_GET['endDate'] ?? '';

    // START_STAGE_DATE is required by the GP-API
    $start = $startDate
        ? (new DateTime($startDate))->setTime(0, 0, 0)
        : (new DateTime())->modify('-90 days')->setTime(0, 0, 0);

    $query = ReportingService::findDisputesPaged($page, $pageSize)
        ->orderBy(DisputeSortProperty::ID, SortDirection::DESC)
        ->where(DataServiceCriteria::START_STAGE_DATE, $start);

    if ($endDate) {
        $query = $query->andWith(
            DataServiceCriteria::END_STAGE_DATE,
            (new DateTime($endDate))->setTime(23, 59, 59)
        );
    }

    if ($status) {
        $query = $query->andWith(SearchCriteria::DISPUTE_STATUS, $status);
    }

    if ($stage) {
        $query = $query->andWith(SearchCriteria::DISPUTE_STAGE, $stage);
    }

    $result = $query->execute();

    $disputes = array_map(function ($d) {
        return [
            'caseId'                     => $d->caseId,
            'caseStatus'                 => $d->caseStatus,
            'caseStage'                  => $d->caseStage,
            'caseAmount'                 => $d->caseAmount,
            'caseCurrency'               => normalizeCurrency($d->caseCurrency),
            'transactionCardType'        => $d->transactionCardType,
            'transactionMaskedCardNumber' => $d->transactionMaskedCardNumber,
            'transactionARN'             => $d->transactionARN,
            'respondByDate'              => $d->respondByDate?->format(DateTime::ATOM),
            'depositDate'                => !empty($d->depositDate)
                    ? (new DateTime($d->depositDate))->format(DateTime::ATOM)
                    : null,
            'caseIdTime'                 => $d->caseIdTime?->format(DateTime::ATOM),
        ];
    }, $result->result ?? []);

    echo json_encode([
        'disputes'    => $disputes,
        'totalCount'  => $result->totalRecordCount ?? count($disputes),
        'totalPages'  => $result->totalRecordCount > 0
            ? (int) ceil($result->totalRecordCount / $pageSize)
            : 1,
        'currentPage' => $page,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => ['code' => $e->getCode() ?: '500', 'message' => $e->getMessage()]]);
}
