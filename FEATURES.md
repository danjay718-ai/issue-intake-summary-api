# Issue Intake and Smart Summary System — Feature List

---

## Core Features

### 1. Issue Management
- Create a support issue with title, description, priority, category, and status
- List all issues with combinable filters (status, priority, category)
- Pagination on the list endpoint
- View a single issue with all its comments (eager loaded — no N+1)
- Update an issue partially (PATCH) — only send what changed
- Soft delete foundation — `deleted_at` column and scope in place (no delete endpoint per scope)

### 2. Comment Management
- Add a comment to an existing issue
- Comments require `author_name` and `body` — both non-empty
- Comments are not editable (no `updated_at` by design)

### 3. Validation & Business Logic
- Reject missing `title`, `description`, `priority`, or `category` on issue creation
- Reject invalid `priority` values — only `low`, `medium`, `high` accepted
- Reject invalid `status` values — only `open`, `in_progress`, `resolved` accepted
- Reject comments with missing or empty `author_name` or `body`
- Trim whitespace — empty strings after trimming are rejected
- Default new issues to `status = open`
- Default `summary_status = pending` on creation
- `needs_attention = true` automatically set when priority is `high`
- `needs_attention` recomputed when priority changes on update

### 4. Authentication
- Token-based API authentication via Laravel Sanctum v4.3
- `POST /api/v1/auth/register` — create account, returns API token
- `POST /api/v1/auth/login` — verify credentials, returns API token
- `POST /api/v1/auth/logout` — revoke current token (requires auth)
- Tokens named `api-token` in the `personal_access_tokens` table
- Only the plain-text token is returned once on creation — Sanctum stores only the hash
- Logout revokes only the current token; other sessions remain active
- Unauthenticated requests return `401` with a consistent JSON error envelope
- All issue and comment routes require a valid token

### 5. Consistent API Responses
- Uniform JSON success shape across all endpoints
- Uniform JSON error shape across all endpoints — never just a 500
- Correct HTTP status codes: 200, 201, 202, 204, 400, 401, 404, 422, 500

---

## Async / Background Job

### 5. Summary Generation Job
- On issue create — job dispatched immediately, API returns `201` with `summary_status = pending`
- Summary and suggested next action generated in the background — never blocks the HTTP request
- On issue update — job re-triggered **only if `description` changes** (title or status change alone does not re-trigger)
- On re-trigger — `summary` reset to `null`, `summary_status` reset to `pending` before job runs
- On job completion — `summary`, `suggested_next_action`, and `summary_status = ready` updated
- On job failure — `summary_status = failed` set, API never affected

---

## Smart Summary and Automation Layer

### 6. Multi-Provider Fallback Chain
- **Anthropic (Claude)** — tried first if `ANTHROPIC_API_KEY` is set
- **OpenAI (GPT)** — tried second if `OPENAI_API_KEY` is set
- **Gemini (Google)** — tried third if `GEMINI_API_KEY` is set
- **Rules-Based Engine** — final fallback, always available, fully offline, no API key needed

### 7. Rules-Based Engine
- Deterministic summary and next action generated from category, priority, and keywords in description
- Always produces a result — system never fails to generate a summary
- Designed behind the same `SummaryGeneratorInterface` so an LLM driver can be swapped in at any time

### 8. SummaryGeneratorInterface
- Clean interface that all providers implement
- Job calls the interface — does not care which driver runs behind it
- Makes the system testable, extendable, and maintainable

### 9. Prompt Template (LLM Providers)
- Committed to the repository
- Consistent structure: title, category, priority, description as input
- Output: 1–2 sentence summary + single concrete next action

---

## Observability

### 10. Summary Generation Log
- Every generation attempt logged regardless of provider or outcome
- Log captures: `issue_id`, `provider`, `status`, `prompt`, `response`, `error_message`, `duration_ms`, `created_at`
- Enables debugging, audit trail, and provider performance insight
- Logs persist even when a provider fails — full history available

---

## Queue & Reliability

### 11. Queue Configuration
- `database` driver for local development — no Redis required
- `sync` driver for testing — fast and deterministic
- Queue worker documented in README — `php artisan queue:work`

### 12. Retry & Failed Job Handling
- Job retries automatically before marking as failed
- Failed jobs handled gracefully — API never crashes
- Dead-letter / failed jobs story documented in README

---

## Database

### 13. Issues Table
- Fields: `id`, `title`, `description`, `priority`, `category`, `status`, `summary`, `suggested_next_action`, `summary_status`, `needs_attention`, `deleted_at`, `created_at`, `updated_at`
- `summary` and `suggested_next_action` are nullable — null until job completes
- `summary_status` tracks state: `pending` → `ready` or `failed`
- `summary_status` defaults to `pending` at the database level (`->default('pending')` in migration) — also set explicitly in the controller on create and reset on description change
- `status` defaults to `open` at the database level — also set explicitly in the controller on create

### 14. Comments Table
- Fields: `id`, `issue_id` (FK), `author_name`, `body`, `created_at`
- No `updated_at` — comments are immutable by design

### 15. Summary Generation Logs Table
- Fields: `id`, `issue_id`, `provider`, `status`, `prompt`, `response`, `error_message`, `duration_ms`, `created_at`

### 16. Migrations & Seeders
- Full migrations for all three tables
- Seed script with 5+ issues spanning all priorities, categories, and statuses
- Seed includes a handful of comments across issues

---

## Testing

### 17. Automated Test Coverage
- **Successful issue creation:** Checks that 201 status is returned, fields are correct, and summary job is dispatched.
- **Validation failure:** Verifies that invalid data returns 422 status with a consistent error shape.
- **Combined list filters:** Ensures `status` and `priority` filters work concurrently.
- **Comment creation:** Confirms a comment can be successfully linked to an issue.
- **N+1 query prevention:** Asserts single issue loading with comments keeps database query counts flat.
- **Async job dispatch:** Validates that creating an issue triggers background summary generation.
- **Job execution:** Confirms the background worker updates the issue status and populates summaries.
- **Regeneration rule:** Verifies description updates trigger new jobs, while status/priority changes do not.
- **Attention status flag:** Asserts priority changes update the `needs_attention` flag accordingly.
- **Fallback resilience:** Verifies failure of one AI provider cascades correctly to the next provider.
- **Log audits:** Confirms every summary generation attempt records structured logs.

**Total: 10 named tests, ~48 assertions** across `IssueApiTest` (8 tests) and `SummaryGenerationTest` (2 tests).

---

## DevOps / Setup

### 18. Docker Compose Support
- Optional setup using `docker compose up` to run the environment.
- Simplifies dependency setup for environments that prefer containerized execution.

### 19. Soft Deletes Foundation
- `deleted_at` column added to issues table
- Global scope filters soft-deleted issues from all queries
- Issues are business records, meaning soft deletion protects historical data from accidental deletion.

---

## API Endpoints

| Method | Endpoint | Auth required | Description |
|---|---|---|---|
| POST | `/api/v1/auth/register` | No | Create a new account, returns token |
| POST | `/api/v1/auth/login` | No | Verify credentials, returns token |
| POST | `/api/v1/auth/logout` | Yes | Revoke current token |
| POST | `/api/v1/issues` | Yes | Create a new issue |
| GET | `/api/v1/issues` | Yes | List issues with optional filters |
| GET | `/api/v1/issues/{id}` | Yes | View one issue with comments |
| PATCH | `/api/v1/issues/{id}` | Yes | Update an issue |
| POST | `/api/v1/issues/{id}/comments` | Yes | Add a comment to an issue |

---

## Future Roadmap Considerations

| Item | Description / Path Forward |
|---|---|
| DELETE Endpoint | Soft deletes are configured. Deletion APIs can be exposed if needed. |
| Real-time Notifications | WebSockets or SSE can be added to notify clients of summary generation. |
| Authentication & Authorization | Can be integrated using Laravel Sanctum or Passport for production deployment. |
| Frontend Interface | The API is client-agnostic and ready to support React, Vue, or mobile frontends. |
