# Architecture & Decisions
## Issue Intake and Smart Summary System

**Date:** 2026-06-05
**Source of truth:** Project Requirements Baseline
**Implementation status:** Backend API implemented and verified

---

## 1. Project Context

This project is a backend API for support and operations issue intake. Users can create, list, view, and update issues. Team members can add comments. A background job generates a short summary and a suggested next action for each issue using a provider fallback chain.

The architecture is intentionally aligned to the agreed requirements baseline:

- Backend API only
- No frontend in the current scope
- API token authentication implemented using Laravel Sanctum
- Versioned REST API endpoints under `/api/v1`
- SQLite as the default local database
- Database queue locally, sync queue in tests
- Soft delete foundation on issues, with no delete endpoint exposed
- Optional AI provider keys, with rules-based fallback always available

---

## 2. Technology Decisions

### Backend Framework: Laravel

**Decision:** Use Laravel as the backend framework.

**Reasoning:** Laravel provides first-party support for the key requirements: routing, request validation, Eloquent ORM, migrations, queues, jobs, database-backed failed jobs, HTTP client integrations, and PHPUnit testing through `php artisan test`.

### Database: SQLite Locally, PostgreSQL Production Path

**Decision:** Use SQLite as the default local database.

**Reasoning:** SQLite keeps reviewer setup simple. The requirements call for a project that can run from a clean machine using README instructions, and SQLite avoids creating database users, credentials, or server processes.

**Production path:** PostgreSQL is the recommended production database for better concurrency, operational tooling, indexing, and long-term data integrity in an eCommerce or operations context.

### Queue Driver: Database Locally, Sync in Tests

**Decision:** Use Laravel's `database` queue driver locally and `sync` in tests.

**Reasoning:** Redis is treated as a future production upgrade, not the default local implementation. The database queue satisfies asynchronous execution without requiring Redis on a reviewer machine. The `sync` queue driver keeps automated tests fast and deterministic.

**Current configuration:**

```env
QUEUE_CONNECTION=database
```

**Test configuration:**

```xml
<env name="QUEUE_CONNECTION" value="sync"/>
```

**Production upgrade path:** Redis with Laravel Horizon can be added later for queue monitoring, throughput, worker balancing, and retry visibility.

---

## 3. API Design

**Decision:** RESTful JSON API with consistent response shapes.

**Current API version:** `v1`

**Base path:** `/api/v1`

| Method | Endpoint | Auth required | Purpose |
|---|---|---|---|
| POST | `/api/v1/auth/register` | No | Create account, return token |
| POST | `/api/v1/auth/login` | No | Verify credentials, return token |
| POST | `/api/v1/auth/logout` | Yes | Revoke current token |
| POST | `/api/v1/issues` | Yes | Create an issue |
| GET | `/api/v1/issues` | Yes | List issues with filters and pagination |
| GET | `/api/v1/issues/{id}` | Yes | View one issue with comments |
| PATCH | `/api/v1/issues/{id}` | Yes | Partially update an issue |
| POST | `/api/v1/issues/{id}/comments` | Yes | Add a comment |
| GET | `/api/v1/issues/{id}/summary-logs` | Yes | View summary generation attempts for an issue |
| GET | `/api/v1/summary-logs` | Yes | List all summary generation logs (global audit trail) |

> [!NOTE]
> The summary log audit endpoints (`/api/v1/summary-logs` and `/api/v1/issues/{id}/summary-logs`) were added purely to allow auditing and debugging of the LLM summary generation attempts. They are optional additions and can be fully removed if requested.

Success response shape:

```json
{
  "success": true,
  "message": "Issue created",
  "data": {}
}
```

Error response shape:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {}
}
```

---

## 4. Authentication — Laravel Sanctum

**Decision:** Implement API token authentication using Laravel Sanctum.

### Why Sanctum

| Factor | Detail |
|---|---|
| First-party package | Maintained by the Laravel core team — no third-party risk |
| Minimal setup | No additional infrastructure; tokens stored in `personal_access_tokens` table |
| Stateful token revocation | Tokens are rows in the database — deleting the row revokes access immediately, important for an operations context |
| Single-service fit | Sanctum is purpose-built for single internal APIs; the scope does not require multi-service stateless tokens |
| Laravel 13 compatible | Ships as a first-class supported package alongside the framework version in use |

### Token Lifecycle

1. **Register** — `POST /api/v1/auth/register` creates a user account and returns a `plainTextToken`.
2. **Login** — `POST /api/v1/auth/login` verifies credentials via `Auth::attempt()`. On success, a new `api-token` is issued and returned.
3. **Authenticated requests** — The client sends `Authorization: Bearer {token}` on all protected routes.
4. **Logout** — `POST /api/v1/auth/logout` calls `currentAccessToken()->delete()`, revoking only the token used for that request. Other sessions remain active.

### Token Storage

Tokens are named `api-token` in the `personal_access_tokens` table. The plain-text token is returned once on creation and never stored. Sanctum stores only the hashed value in the database.

### Route Protection

All issue and comment routes are protected by the `auth:sanctum` middleware. Register and login are intentionally public:

```
Public  → POST /api/v1/auth/register
         POST /api/v1/auth/login

Protected (auth:sanctum) → POST   /api/v1/auth/logout
                            GET    /api/v1/issues
                            POST   /api/v1/issues
                            GET    /api/v1/issues/{id}
                            PATCH  /api/v1/issues/{id}
                            POST   /api/v1/issues/{id}/comments
                            GET    /api/v1/issues/{id}/summary-logs
                            GET    /api/v1/summary-logs
```

### Exception Handling

Unauthenticated requests to protected routes return a consistent JSON 401 via an explicit `AuthenticationException` handler registered in `bootstrap/app.php`:

```json
{ "success": false, "message": "Unauthenticated", "errors": [] }
```

### Future Upgrade Path — JWT

When the system grows to serve multiple services or requires stateless token validation (no database hit per request), the correct upgrade path is **JWT via `tymon/jwt-auth`**:

- Same interface contract — swap the auth driver without changing route or controller code
- Stateless — no `personal_access_tokens` table query per request
- Standard — interoperable across services and languages

JWT is documented as a roadmap item, not a current requirement. Sanctum is the correct fit for the current single-service scope.

---

## 5. Schema Decisions

### Issues Table

Fields:

- `id`
- `title`
- `description`
- `priority`: `low`, `medium`, `high`
- `category`
- `status`: `open`, `in_progress`, `resolved`
- `summary`
- `suggested_next_action`
- `summary_status`: `pending`, `ready`, `failed`
- `needs_attention`
- `deleted_at`
- `created_at`
- `updated_at`

Key choices:

- `summary` and `suggested_next_action` are nullable until the background job completes.
- `summary_status` defaults to `pending` on creation at the database level (`->default('pending')` in the migration). It is also set explicitly in the controller on create, and reset to `pending` on description change.
- `status` defaults to `open` at the database level. It is also set explicitly in the controller on create.
- `needs_attention` is set synchronously when priority is high.
- `deleted_at` exists for soft delete foundation, but no delete route is exposed.

### Comments Table

Fields:

- `id`
- `issue_id`
- `author_name`
- `body`
- `created_at`

Key choices:

- Comments are immutable.
- There is no `updated_at` column.
- Comments belong to issues and are returned only on the single issue view.

### Summary Generation Logs Table

Fields:

- `id`
- `issue_id`
- `provider`
- `status`
- `prompt`
- `response`
- `error_message`
- `duration_ms`
- `created_at`

Key choices:

- Every attempted provider call is logged.
- Skipped providers are not logged because no attempt was made.
- Rules-based generation logs a `rules_based` success with `prompt = null`.

---

## 6. Soft Delete Decision

**Decision:** Implement the soft delete foundation with `deleted_at` and Laravel's `SoftDeletes` trait on the `Issue` model.

**What is implemented:**

- `deleted_at` column exists on `issues`
- `Issue` uses `SoftDeletes`
- Eloquent queries exclude soft-deleted issues by default

**What is not implemented:**

- No `DELETE /api/v1/issues/{id}` endpoint
- No restore endpoint
- No hard delete endpoint

**Reasoning:** The current requirements need the soft delete foundation but no exposed delete endpoint. This keeps the current API at the required five endpoints while preserving a safe future path.

---

## 7. Async Summary Generation

**Decision:** Generate summaries only in `GenerateSummaryJob`.

Create flow:

1. Validate request.
2. Save issue immediately with `summary_status = pending`.
3. Dispatch `GenerateSummaryJob`.
4. Return `201` without waiting for AI generation.

Update flow:

1. Validate request.
2. Check whether `description` changed.
3. If description changed, reset summary fields and set `summary_status = pending`.
4. Dispatch a new `GenerateSummaryJob`.
5. If only status, title, priority, or category changed, do not regenerate.

Failure behavior:

- Job has `$tries = 3`
- Job has `$timeout = 60`
- `failed()` sets `summary_status = failed`
- Failed jobs do not affect API responses

---

## 8. AI and Automation Layer

### Interface

All generators implement `SummaryGeneratorInterface`.

The job depends on `SummaryGeneratorChain`, not on individual providers. This keeps the job stable when providers are added, removed, or reordered.

### Provider Order

1. Anthropic, if `ANTHROPIC_API_KEY` is configured
2. OpenAI, if `OPENAI_API_KEY` is configured
3. Gemini, if `GEMINI_API_KEY` is configured
4. Rules-based generator, always available

If a provider is not configured, it is skipped. If a configured provider fails, the failure is logged and the chain tries the next provider.

### Rules-Based Fallback

The rules-based generator uses:

- category mapping
- priority wording
- keyword matching in description

It always returns:

- a 1-2 sentence summary
- one concrete suggested next action

This satisfies the requirement that the app works locally without AI keys.

### Prompt Template

The prompt template is committed at:

`resources/prompts/summary.txt`

LLM providers request JSON with:

```json
{
  "summary": "...",
  "suggested_next_action": "..."
}
```

---

## 9. Validation Decisions

Validation is handled through Laravel Form Requests:

- `StoreIssueRequest`
- `UpdateIssueRequest`
- `StoreCommentRequest`

Rules:

- `title`: required on create, non-empty after trim, max 255
- `description`: required on create, non-empty after trim
- `priority`: `low`, `medium`, `high`
- `category`: required on create, non-empty after trim, max 255
- `status`: `open`, `in_progress`, `resolved`
- `author_name`: required, non-empty after trim, max 255
- `body`: required, non-empty after trim

All validation errors return HTTP 422 with the shared error shape.

---

## 10. HTTP Status Codes

The API returns the following HTTP status codes:

| Code | When returned |
|---|---|
| `200` | Successful GET (list or single issue) and successful PATCH |
| `201` | Successful POST — issue created or comment added |
| `202` | Reserved for future async endpoints (not currently used) |
| `204` | Reserved for future no-content responses (not currently used) |
| `400` | Malformed request — caught by the global exception handler |
| `404` | Issue or resource not found — Laravel model binding returns 404 automatically |
| `422` | Validation failure — returned by all Form Requests with the shared error envelope |
| `500` | Unhandled server error — caught by the global exception handler |

The `ApiResponse` trait builds success envelopes and accepts any status code. The default success code is `200`; controllers pass `201` explicitly where required.

---

## 11. Filtering and Pagination

### Combinable Filters

The list endpoint (`GET /api/v1/issues`) supports three optional, independently combinable query parameters:

| Parameter | Values | Behavior |
|---|---|---|
| `status` | `open`, `in_progress`, `resolved` | Filters by exact status match |
| `priority` | `low`, `medium`, `high` | Filters by exact priority match |
| `category` | any string | Filters by exact category match |

Filters are additive — all supplied filters are applied together using `AND` logic:

```
GET /api/v1/issues?status=open&priority=high&category=billing
```

Omitting a parameter removes that constraint entirely. Each filter uses Laravel's `when()` builder so unset parameters are skipped without conditional branching in the controller.

### Pagination

The list endpoint always returns paginated results. The pagination shape is Laravel's standard paginator output, nested under `data` in the success envelope:

```json
{
  "success": true,
  "message": "Issues retrieved",
  "data": {
    "current_page": 1,
    "data": [ /* issue objects */ ],
    "per_page": 15,
    "total": 42,
    "last_page": 3,
    "next_page_url": "...",
    "prev_page_url": null
  }
}
```

| Query parameter | Default | Description |
|---|---|---|
| `per_page` | `15` | Number of results per page |
| `page` | `1` | Page number to retrieve |

---

## 12. N+1 Prevention

The single issue endpoint loads comments with:

```php
$issue->load('comments')
```

The list endpoint intentionally excludes comments so pagination stays lightweight.

Test coverage asserts the single issue view performs a flat query count regardless of comment volume.

---

## 13. Queue Worker

**Decision:** Use the `database` queue driver locally and document the worker start command explicitly.

To process queued jobs locally, run the worker in a second terminal after starting the server:

```bash
php artisan queue:work
```

The worker polls the `jobs` table and executes `GenerateSummaryJob` entries as they are enqueued. Without the worker running, issues will be created successfully (API still returns `201`) but `summary_status` will remain `pending` indefinitely.

In the Docker Compose setup, the worker is started automatically inside the container via the entrypoint script — no manual step is needed.

**Test environment:** Tests use `QUEUE_CONNECTION=sync`, which runs the job inline and does not require a running worker.

---

## 14. Seeders

The project ships with deterministic seeders for reviewer and demo use.

**Command to seed:**

```bash
php artisan db:seed
```

Or as part of migration:

```bash
php artisan migrate --seed
```

### `IssueSeeder`

Creates 5 issues spanning all required priorities, categories, and statuses:

| Title | Priority | Category | Status |
|---|---|---|---|
| Incorrect invoice total | high | billing | open |
| Checkout crashes | high | bug | in_progress |
| Bulk export request | medium | feature-request | open |
| Warehouse access restored | low | access | resolved |
| Slow order search | medium | performance | in_progress |

Each issue is created with `summary_status = pending` and `needs_attention` set automatically based on priority.

### `CommentSeeder`

Adds 5 comments across the first three seeded issues to simulate team activity:

- Issue 1 (billing): 2 comments (Ava, Noah)
- Issue 2 (bug): 2 comments (Mia, Leo)
- Issue 3 (feature-request): 1 comment (Ivy)

`CommentSeeder` runs after `IssueSeeder` since it references issue IDs. Both are called from `DatabaseSeeder`.

---

## 15. Test Plan

The automated test suite uses PHPUnit via `php artisan test`. Tests run against a fresh in-memory database (using `RefreshDatabase`) with the `sync` queue driver so jobs execute inline.

**Run all tests:**

```bash
php artisan test
```

### `IssueApiTest` — API behaviour

| Test | What it verifies |
|---|---|
| `test_valid_issue_create_returns_pending_issue_and_dispatches_job` | POST returns 201, `summary_status = pending`, `needs_attention = true` for high priority, job dispatched |
| `test_issue_create_validation_failure_returns_422_and_saves_nothing` | Invalid payload returns 422 with error shape, nothing saved, no job dispatched |
| `test_issue_list_combines_status_and_priority_filters` | Combined `?status=open&priority=high` filter returns only the matching issue |
| `test_comment_can_be_added_to_existing_issue` | POST to comments returns 201, input is trimmed, record saved |
| `test_single_issue_view_eager_loads_comments_without_n_plus_one` | Single issue with 5 comments produces exactly 2 DB queries regardless of comment count |
| `test_description_update_retriggers_job_and_resets_summary` | PATCH with new description nulls summary fields, sets `summary_status = pending`, dispatches new job |
| `test_status_update_does_not_retrigger_job` | PATCH with only status change does not dispatch a job |
| `test_priority_controls_needs_attention_on_create_and_update` | `needs_attention` is set correctly on create and recomputed on priority update |

### `SummaryGenerationTest` — background job behaviour

| Test | What it verifies |
|---|---|
| `test_running_job_populates_summary_fields_and_log` | Dispatching the job updates `summary_status = ready`, populates `summary` and `suggested_next_action`, and writes a success log |
| `test_fallback_chain_logs_failure_then_tries_next_provider` | A failing provider is logged as failed, the chain continues, and the rules-based generator succeeds |

### `SummaryLogApiTest` — log retrieval and filtering

| Test | What it verifies |
|---|---|
| `test_get_summary_logs_for_issue` | GET returns log history for a specific issue, sorted by ID descending |
| `test_get_global_summary_logs` | GET returns paginated logs, supports filtering by provider and status |

**Total: 20 named tests, 91 assertions.**

## 16. Current Stack Summary

| Layer | Current Choice | Notes |
|---|---|---|
| Framework | Laravel 13 | PHP backend API |
| Runtime | PHP 8.5 locally | Project requires PHP 8.2+ |
| Database | SQLite | Default local setup |
| Production DB path | PostgreSQL | Future production recommendation |
| Queue local | Database queue | Matches local scope |
| Queue tests | Sync | Deterministic tests |
| AI providers | Anthropic, OpenAI, Gemini | Optional keys |
| Offline fallback | Rules-based generator | Always available |
| Auth | Laravel Sanctum v4.3 | Token-based, DB-backed revocation |
| Soft deletes | Foundation implemented | No delete endpoint |
| Tests | PHPUnit via Laravel | `php artisan test` |

---

## 17. Future Roadmap

To support scale and production deployment, the following roadmap features are recommended:

*   **Security & User Authentication:** Integrate user identity and token-based access controls (e.g., via Laravel Sanctum) to restrict API access to verified staff.
*   **Administrative Management (Soft Delete Utilities):** Build dedicated endpoints and user interfaces to allow administrators to review, restore, or permanently remove soft-deleted issues.
*   **Real-time Notifications:** Add WebSocket or Server-Sent Events (SSE) support to notify client applications immediately when a background summary job completes, avoiding the need for periodic API polling.
*   **Production Queue Optimization:** Transition from database-backed queues to Redis (managed via Laravel Horizon) to scale concurrent worker throughput and gain real-time queue performance analytics.
*   **Concurrent Edit Protection:** Implement optimistic locking on issue update endpoints to handle instances where multiple support staff attempt to edit the same ticket details concurrently.
*   **Staff UI Interface:** Build a responsive web application (using React, Vue, or blade-based templates) to give support staff a graphical user dashboard.

---

## 18. System Design Principles

The architecture is designed to satisfy the core requirements baseline:
- **Simple Local Setup:** Utilizes an SQLite database and a database-backed queue to minimize environment dependencies.
- **Robust Fallback Capability:** Implements a local rules-based summary engine to handle cases where external API keys are absent or API limits are reached.
- **Standardized API Contracts:** Exposes consistent JSON response shapes for successes and failures.
- **Structured Logging:** Tracks all external integration attempts for operational visibility.
