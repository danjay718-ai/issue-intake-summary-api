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
- No authentication in the current scope
- Five versioned REST API endpoints under `/api/v1`
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

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/v1/issues` | Create an issue |
| GET | `/api/v1/issues` | List issues with filters and pagination |
| GET | `/api/v1/issues/{id}` | View one issue with comments |
| PATCH | `/api/v1/issues/{id}` | Partially update an issue |
| POST | `/api/v1/issues/{id}/comments` | Add a comment |

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

## 4. Authentication Decision

**Decision:** Authentication is not implemented.

**Reasoning:** Authentication and authorization are out of scope for the current deliverable. The assessment is focused on backend API behavior, issue/comment management, async generation, fallback behavior, observability, and tests.

**Future enhancement:** Laravel Sanctum is the recommended path for API token authentication if this becomes a production service.

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
- `summary_status` makes summary generation state explicit for polling clients.
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

## 10. N+1 Prevention

The single issue endpoint loads comments with:

```php
$issue->load('comments')
```

The list endpoint intentionally excludes comments so pagination stays lightweight.

Test coverage asserts the single issue view performs a flat query count regardless of comment volume.

---

## 11. Current Stack Summary

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
| Auth | Not implemented | Out of scope |
| Soft deletes | Foundation implemented | No delete endpoint |
| Tests | PHPUnit via Laravel | `php artisan test` |

---

## 12. Future Roadmap

To support scale and production deployment, the following roadmap features are recommended:

*   **Security & User Authentication:** Integrate user identity and token-based access controls (e.g., via Laravel Sanctum) to restrict API access to verified staff.
*   **Administrative Management (Soft Delete Utilities):** Build dedicated endpoints and user interfaces to allow administrators to review, restore, or permanently remove soft-deleted issues.
*   **Real-time Notifications:** Add WebSocket or Server-Sent Events (SSE) support to notify client applications immediately when a background summary job completes, avoiding the need for periodic API polling.
*   **Production Queue Optimization:** Transition from database-backed queues to Redis (managed via Laravel Horizon) to scale concurrent worker throughput and gain real-time queue performance analytics.
*   **Concurrent Edit Protection:** Implement optimistic locking on issue update endpoints to handle instances where multiple support staff attempt to edit the same ticket details concurrently.
*   **Staff UI Interface:** Build a responsive web application (using React, Vue, or blade-based templates) to give support staff a graphical user dashboard.

---

## 13. System Design Principles

The architecture is designed to satisfy the core requirements baseline:
- **Simple Local Setup:** Utilizes an SQLite database and a database-backed queue to minimize environment dependencies.
- **Robust Fallback Capability:** Implements a local rules-based summary engine to handle cases where external API keys are absent or API limits are reached.
- **Standardized API Contracts:** Exposes consistent JSON response shapes for successes and failures.
- **Structured Logging:** Tracks all external integration attempts for operational visibility.
