# Changelog

All notable project changes are listed here with the newest state first.

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
