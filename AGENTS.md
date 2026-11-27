# Dispute Management

> Manage GP-API chargebacks: list, detail, accept, and challenge with evidence upload.

## Critical Patterns

1. **`findDisputesPaged` requires `START_STAGE_DATE`.** Every list query must include this filter or the API returns an error. The UI defaults to 90 days back; all backends enforce a fallback if the query param is missing.

2. **Accept and challenge only need a `caseId` — do not prefetch.** Construct a `DisputeSummary` directly, set `caseId`, then call `.accept()` or `.challenge($documents)`. Do **not** call `ReportingService::disputeDetail()` first for these actions — it adds an unnecessary API round-trip. The detail fetch is only needed for the display view.
   - PHP: `$dispute = new DisputeSummary(); $dispute->caseId = $id;`
   - Node.js: `const dispute = new DisputeSummary(); dispute.caseId = id;`
   - .NET: `var dispute = new DisputeSummary { CaseId = id };`
   - Java: `DisputeSummary dispute = new DisputeSummary(); dispute.setCaseId(id);`

3. **`DisputeDocument` requires `b64_content` (PHP) / `b64Content` (Node/Java/.NET).** The frontend converts uploaded files to base64 before POSTing. The `type` field is optional (defaults accepted) but setting it (e.g., `SALES_RECEIPT`) improves routing on the GP-API side.

4. **Sandbox Case IDs for testing:** `DIS_SAND_abcd1234` (accept/single-doc challenge), `DIS_SAND_abcd1241` (multi-doc challenge). These are pre-seeded in every GP-API sandbox account.

5. **Only `WITH_MERCHANT` disputes take an accept or a challenge.** The GP-API sandbox accepts both for the `WITH_MERCHANT` cases (`DIS_SAND_abcd1234` to `DIS_SAND_abcd1241`) and answers `SYSTEM_ERROR_DOWNSTREAM` for `UNDER_REVIEW` and `CLOSED` ones, so the frontend only shows the buttons for `WITH_MERCHANT`. Accept and challenge call the GP-API in every environment. The `GET /config` endpoint returns the current environment so the frontend can show a sandbox mode banner.

6. **Dispute stage values from the API.** The GP-API can return these stage strings — not all are in the SDK enum constants by that exact name:
   `RETRIEVAL`, `CHARGEBACK`, `SECOND_CHARGEBACK`, `PRE_ARBITRATION`, `ARBITRATION`, `PRE_COMPLIANCE`, `COMPLIANCE`, `REVERSAL`, `GOOD_FAITH` (SDK enum uses `GOODFAITH` without underscore, but the API returns `GOOD_FAITH`). The frontend `stageBadge()` handles both spellings.

## Repository Structure

### PHP (built-in server, PHP 8.2.35+, 8.3.36+, 8.4.27+, or 8.5.12+)
- [`php/router.php`](php/router.php) — request dispatcher; routes to `php/api/` handlers
- [`php/api/config.php`](php/api/config.php) — `GET /config`
- [`php/api/list-disputes.php`](php/api/list-disputes.php) — `GET /disputes`
- [`php/api/dispute-detail.php`](php/api/dispute-detail.php) — `GET /disputes/{id}`
- [`php/api/accept-dispute.php`](php/api/accept-dispute.php) — `POST /disputes/{id}/accept`
- [`php/api/challenge-dispute.php`](php/api/challenge-dispute.php) — `POST /disputes/{id}/challenge`
- [`php/sdk-config.php`](php/sdk-config.php) — `GpApiConfig` bootstrap shared across handlers

### Node.js (Express, Node 24+)
- [`nodejs/server.js`](nodejs/server.js) — all routes in a single Express app

### .NET (ASP.NET Core Minimal API, .NET 10)
- [`dotnet/Program.cs`](dotnet/Program.cs) — all routes via `app.MapGet` / `app.MapPost`

### Java (Servlet + embedded Tomcat, Java 21)
- [`java/src/main/java/com/globalpayments/example/DisputeManagementServlet.java`](java/src/main/java/com/globalpayments/example/DisputeManagementServlet.java)

### Shared
- [`index.html`](index.html) — canonical frontend source; each language directory has its own copy (`php/index.html`, `nodejs/index.html`, etc.) served at `/` by that backend
- [`docker-compose.yml`](docker-compose.yml) — PHP `:8003`, Node `:8001`, .NET `:8006`, Java `:8004`

## API Surface

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/config` | Backend health + environment |
| GET | `/disputes` | Paged list (`page`, `pageSize`, `status`, `stage`, `startDate`, `endDate`) |
| GET | `/disputes/{id}` | Full `DisputeSummary` detail |
| POST | `/disputes/{id}/accept` | Accept dispute |
| POST | `/disputes/{id}/challenge` | Challenge with `{ documents: [{type, b64Content}] }` |

## Environment Variables

| Variable | Description |
|----------|-------------|
| `GP_APP_ID` | GP-API application ID |
| `GP_APP_KEY` | GP-API application key |
| `GP_ENVIRONMENT` | `sandbox` (default) or `production` |
