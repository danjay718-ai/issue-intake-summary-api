# Changelog

All notable project changes are listed here with the newest state first.

---

## 2026-06-05 — Summary Generation Logs Audit Endpoints and Priority Fix

### Added
- `SummaryLogController` — Exposes the append-only summary generation logs table through two RESTful JSON endpoints:
  - `GET /api/v1/issues/{issue}/summary-logs` (step-by-step history for a specific issue)
  - `GET /api/v1/summary-logs` (global audit log table with pagination and filters by provider/status)
- `SummaryLogApiTest` — Verifies authentication, pagination, structure, and filters for all summary log audit endpoints.

### Changed
- `AppServiceProvider` — Reordered the fallback chain to promote Gemini first, so that the application attempts Gemini generation immediately without failing on non-configured Anthropic/OpenAI providers.
- `SummaryGenerationLog` model — Added `$casts` property for `created_at => datetime` to ensure Carbon instances are returned, resolving ISO8601 formatting serialization issues.
- `SummaryLogController` — Configured sorting by ID descending (`latest('id')`) instead of `created_at` to guarantee deterministic, chronological ordering when logs are generated in the same second.
- `routes/api.php` — Protected all log audit endpoints with Sanctum (`auth:sanctum` middleware).
- `ARCHITECTURE.md` — Updated API Design tables, route protection diagrams, and testing section with the new endpoints and test cases.
- `FEATURES.md` — Updated Observability and Testing specifications.

### Verified
- Executed all 20 tests in the test suite (`./vendor/bin/phpunit`). All tests passed successfully (91 assertions).

---

## 2026-06-05 — API Token Authentication via Laravel Sanctum

### Added
- Installed `laravel/sanctum` v4.3.2 and published the `personal_access_tokens` migration.
- Added `HasApiTokens` trait to `User` model to enable token issuance and revocation.
- `AuthController` — handles register, login, and logout with a consistent `ApiResponse` envelope.
- `StoreRegisterRequest` — validates name, unique email, and confirmed password (min 8 chars).
- `StoreLoginRequest` — validates email format and non-empty password before credential check.
- `AuthTest` — 7 named tests covering: register success, duplicate email 422, login success, wrong password 401, unauthenticated 401, authenticated 200, and logout token revocation 401.
- `error()` method added to `ApiResponse` trait for consistent non-success envelopes.

### Changed
- `routes/api.php` restructured into two groups: public (register, login) and protected (`auth:sanctum` middleware on logout + all 5 issue/comment routes).
- `bootstrap/app.php` — added explicit `ValidationException` (422) and `AuthenticationException` (401) handlers before the catch-all `Throwable` handler to prevent swallowing as 500.
- `IssueApiTest` and `SummaryGenerationTest` — added `setUp()` with `actingAs(User::factory()->create(), 'sanctum')` to authenticate before every test.
- `ARCHITECTURE.md` — §4 fully rewritten with Sanctum decision rationale, token lifecycle, route protection map, exception handling, and JWT as the documented future upgrade path.
- `ARCHITECTURE.md` — API endpoint table updated to include all 8 routes with auth requirement column.
- Stack summary table updated: Auth row changed from "Not implemented" to "Laravel Sanctum v4.3".

### Verified
- All 18 tests pass with 71 assertions.

---

## 2026-06-05 — Architecture Documentation Gaps Resolved

### Changed
- Patched `ARCHITECTURE.md` to document all features that were fully implemented in code but missing from the architecture document.

### Added to `ARCHITECTURE.md`
- **§5 Schema Decisions:** Explicit callout that `summary_status` defaults to `pending` at the database level (`->default('pending')` in migration) and is also set in the controller on create and reset on description change. Same clarification added for `status` defaulting to `open`.
- **§10 HTTP Status Codes:** Full reference table for all 8 status codes in use — `200`, `201`, `202`, `204`, `400`, `404`, `422`, `500` — with the trigger condition for each.
- **§11 Filtering and Pagination:** Documented all three combinable filter parameters (`status`, `priority`, `category`) with their accepted values and `AND` logic behavior. Added pagination query params (`page`, `per_page`, default `15`) and the full paginated response shape.
- **§13 Queue Worker:** Documented `php artisan queue:work` as the required command to process background jobs locally. Noted that Docker Compose starts the worker automatically and tests use `sync` driver.
- **§14 Seeders:** Documented `IssueSeeder` (5 issues spanning all priorities, categories, statuses) and `CommentSeeder` (5 comments across 3 issues), including seed commands and the seeder execution order.
- **§15 Test Plan:** Full named test inventory — 8 tests in `IssueApiTest` and 2 in `SummaryGenerationTest` — each with a description of what it verifies.

### Verified
- All documented behaviors confirmed in code before documentation was updated. No code changes were required.

---

## 2026-06-05 — API Versioning and Enhancements

### Added
- Implemented API versioning, routing all endpoints under `/api/v1`.
- Added project documentation including README, architecture details, and feature list.
- Configured Git exclusions for local development and build scratchpad files.
- Added detailed comments and inline documentation to controller methods, form requests, Eloquent models, queues, migrations, and test classes.

### Changed
- Updated API endpoint paths to use `/api/v1` prefix.
- Updated automated feature tests to match versioned API paths.

### Verified
- Automated test suite passes successfully.
- Code style checks pass.

---

## 2026-06-05 — Core Backend API Implementation

### Added
- Core backend API for the Issue Intake and Smart Summary System.
- Implemented five REST API endpoints:
  - `POST /api/v1/issues` (Submit an issue)
  - `GET /api/v1/issues` (List issues with filtering and pagination)
  - `GET /api/v1/issues/{id}` (View issue details with eager-loaded comments)
  - `PATCH /api/v1/issues/{id}` (Partially update issue fields)
  - `POST /api/v1/issues/{id}/comments` (Add a comment to an issue)
- Created database migrations for:
  - `issues` (with support for status, priority, categorization, smart summary, and soft delete tracking)
  - `comments` (immutable record structure)
  - `summary_generation_logs` (attempt history)
- Created Eloquent models (`Issue`, `Comment`, and `SummaryGenerationLog`) with relationships and casts.
- Added Form Requests for validation with automatic input string trimming.
- Standardized API JSON response structures for both success and validation/exception error states.
- Implemented `GenerateSummaryJob` to dispatch summary requests asynchronously.
- Developed the summary generation provider chain (Anthropic, OpenAI, Gemini) with a built-in rules-based offline fallback.
- Added database seeders to populate sample issues and comments.
- Added automated feature tests covering all API flows and background jobs.
- Documented system architecture and features.

### Verified
- Database migrations and seeders execute without error.
- All 11 feature tests pass with 48 assertions.

### Notes
- Confirmed runtime environments support SQLite.

---

## 2026-06-04 — Initial Scaffolding and Setup

### Added
- Scaffolded the base Laravel application.
- Configured local environment templates (`.env.example`).
- Set up SQLite as the default database connection.
- Set up database queue configurations.
