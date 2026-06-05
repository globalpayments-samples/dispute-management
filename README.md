# Dispute Management

Manage chargebacks and disputes through the Global Payments GP API. List open disputes, review case details, accept disputes, and challenge them by uploading evidence documents — all from a single interface backed by any of four language implementations.

## Features

- List and filter disputes by status, stage, and date range
- View full dispute detail — case ID, ARN, masked card, amounts, respond-by date
- Accept a dispute in the cardholder's favor
- Challenge a dispute with evidence document upload (sales receipt, proof of delivery, etc.)
- Paged results with sorting
- PHP, Node.js, .NET, and Java backends — one shared frontend

## Quick Start

```bash
git clone https://github.com/globalpayments-samples/dispute-management.git
cd dispute-management/php   # or nodejs, dotnet, java
cp .env.sample .env
# Add your GP_APP_ID and GP_APP_KEY to .env
./run.sh
```

Open `http://localhost:8000` in your browser.

## Environment Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `GP_APP_ID` | GP-API application ID | `your_app_id` |
| `GP_APP_KEY` | GP-API application key | `your_app_key` |
| `GP_ENVIRONMENT` | `sandbox` or `production` | `sandbox` |

Get credentials at [developer.globalpayments.com](https://developer.globalpayments.com).

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/config` | Returns environment info |
| `GET` | `/disputes` | List disputes (paged, filterable) |
| `GET` | `/disputes/{id}` | Get dispute detail |
| `POST` | `/disputes/{id}/accept` | Accept a dispute |
| `POST` | `/disputes/{id}/challenge` | Challenge with evidence documents |

### List Disputes

`GET /disputes?page=1&pageSize=10&status=UNDER_REVIEW&stage=CHARGEBACK&startDate=2025-01-01&endDate=2025-03-31`

```json
{
  "disputes": [
    {
      "caseId": "DIS_SAND_abcd1234",
      "caseStatus": "WITH_MERCHANT",
      "caseStage": "CHARGEBACK",
      "caseAmount": 10.00,
      "caseCurrency": "USD",
      "transactionCardType": "VISA",
      "transactionMaskedCardNumber": "411111XXXXXX1111",
      "transactionARN": "74500010037624410827759",
      "respondByDate": "2025-04-15T00:00:00",
      "depositDate": "2025-03-01T00:00:00"
    }
  ],
  "totalCount": 1,
  "totalPages": 1,
  "currentPage": 1
}
```

### Get Dispute Detail

`GET /disputes/DIS_SAND_abcd1234`

```json
{
  "dispute": {
    "caseId": "DIS_SAND_abcd1234",
    "caseStatus": "WITH_MERCHANT",
    "caseStage": "CHARGEBACK",
    "caseAmount": 10.00,
    "caseCurrency": "USD",
    "transactionCardType": "VISA",
    "transactionMaskedCardNumber": "411111XXXXXX1111",
    "transactionARN": "74500010037624410827759",
    "respondByDate": "2025-04-15T00:00:00",
    "depositDate": "2025-03-01T00:00:00",
    "caseIdTime": "2025-03-01T10:22:00"
  }
}
```

### Accept a Dispute

`POST /disputes/DIS_SAND_abcd1234/accept`

Production response:
```json
{ "success": true, "responseCode": "SUCCESS" }
```

Sandbox response:
```json
{ "success": true, "responseCode": "SUCCESS", "sandbox": true, "note": "Write operations are simulated in sandbox..." }
```

### Challenge a Dispute

`POST /disputes/DIS_SAND_abcd1234/challenge`

```json
{
  "documents": [
    {
      "type": "SALES_RECEIPT",
      "b64Content": "R0lGODlh..."
    }
  ]
}
```

Response:
```json
{ "success": true, "responseCode": "SUCCESS" }
```

## Test Data

GP-API sandbox pre-seeds the following dispute records for testing:

| Case ID | Stage | Use for |
|---------|-------|---------|
| `DIS_SAND_abcd1234` | CHARGEBACK | Accept or single-document challenge |
| `DIS_SAND_abcd1241` | CHARGEBACK | Multi-document challenge |

> **Note**: The sandbox `findDisputesPaged` endpoint requires a `startDate` filter. The UI defaults to the last 90 days. If no disputes appear, widen the date range.

The sandbox pre-seeds disputes across all nine stage types: `RETRIEVAL`, `CHARGEBACK`, `SECOND_CHARGEBACK`, `PRE_ARBITRATION`, `ARBITRATION`, `PRE_COMPLIANCE`, `COMPLIANCE`, `REVERSAL`, and `GOOD_FAITH`. Use the Stage filter in the UI to filter by specific stages.

## How It Works

```
Browser → GET /disputes (with filters)
       ← List of DisputeSummary objects

Browser → GET /disputes/{id}
       ← Full DisputeSummary detail

Browser → POST /disputes/{id}/accept
       ← Backend constructs DisputeSummary with caseId, calls .accept().execute()
       ← (sandbox: returns mock SUCCESS without calling GP-API)

Browser → POST /disputes/{id}/challenge  (with base64 document)
       ← Backend builds DisputeDocument[], constructs DisputeSummary, calls .challenge(docs).execute()
       ← (sandbox: returns mock SUCCESS without calling GP-API)
```

## Sandbox Mode

Accept and challenge requests are **simulated** when `GP_ENVIRONMENT=sandbox` — they do not reach the card network. The response includes `"sandbox": true` and a note explaining production behavior. The UI shows a yellow banner when running in sandbox mode.

```json
{ "success": true, "responseCode": "SUCCESS", "sandbox": true, "note": "Write operations are simulated in sandbox and not submitted to the card network." }
```

To test real dispute actions against live disputes, set `GP_ENVIRONMENT=production` with production credentials.

## Available Language Backends

| Language | Directory | Start Command |
|----------|-----------|---------------|
| PHP | `php/` | `cd php && ./run.sh` |
| Node.js | `nodejs/` | `cd nodejs && ./run.sh` |
| .NET | `dotnet/` | `cd dotnet && ./run.sh` |
| Java | `java/` | `cd java && ./run.sh` |

## Run with Docker

```bash
cp .env.sample .env  # add credentials
docker compose up php
```

Services run on: PHP `:8003`, Node.js `:8001`, .NET `:8006`, Java `:8004`.

## Security

- GP-API credentials (`GP_APP_ID`, `GP_APP_KEY`) are never sent to the browser
- All SDK calls execute server-side
- `.env` is in `.gitignore`; commit only `.env.sample`
- Input is validated server-side before forwarding to the GP-API

## Troubleshooting

**No disputes in the list**
The `findDisputesPaged` API requires a start date. Widen the date range filter. The UI defaults to 90 days but your sandbox may have older records.

**`INVALID_DISPUTE_ACTION` error on Accept**
The dispute may already be accepted or its status does not allow acceptance. Only `UNDER_REVIEW` disputes can be accepted or challenged.

**`MANDATORY_DATA_MISSING` on Challenge**
A document with no `b64Content` was submitted. Ensure a file is selected before submitting.

**`Unable to locate dispute record` (40073)**
The Case ID does not exist in your sandbox account. Use one of the sandbox IDs in the Test Data table above.

## Resources

- [GP-API Developer Docs](https://developer.globalpayments.com)
- [API Reference](https://developer.globalpayments.com/api/references-overview)
- [PHP SDK](https://github.com/globalpayments/php-sdk)
- [Node.js SDK](https://github.com/globalpayments/node-js-sdk)
- [.NET SDK](https://github.com/globalpayments/dotnet-sdk)
- [Java SDK](https://github.com/globalpayments/java-sdk)
