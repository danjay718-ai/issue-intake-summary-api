# Issue Intake and Smart Summary System

Backend API for submitting operational issues, adding comments, and generating a summary plus suggested next action asynchronously.

Recommended repository name: `issue-intake-summary-api`.

## Requirements

- PHP 8.2+
- Composer
- SQLite PHP extension (`pdo_sqlite`)

No AI keys are required. If no provider keys are configured, the rules-based generator runs fully offline.

## Setup (Docker Compose - Recommended)

If you have Docker and Docker Compose installed, you can build and start the entire stack (API server and queue worker) with a single command:

```bash
docker compose up --build
```

This will automatically:
- Spin up the web container and mount the local files.
- Set up the SQLite database and generate the application key.
- Run migrations and seed sample issues/comments.
- Expose the API server on `http://127.0.0.1:8000`.
- Start the queue worker process in the background.

To stop the services:

```bash
docker compose down
```

## Setup (Manual Local Run)

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

Run the API:

```bash
php artisan serve
```

Run the queue worker in a second terminal:

```bash
php artisan queue:work
```

Run tests:

```bash
php artisan test
```

## Environment

The default local database is SQLite:

```env
DB_CONNECTION=sqlite
QUEUE_CONNECTION=database
```

Optional AI provider keys:

```env
ANTHROPIC_API_KEY=
OPENAI_API_KEY=
GEMINI_API_KEY=
```

Provider order is Anthropic, OpenAI, Gemini, then rules-based fallback.

## API Versioning

The current public API version is `v1`.

Base API path:

```text
/api/v1
```

## API

All success responses use:

```json
{ "success": true, "message": "...", "data": {} }
```

All validation and error responses use:

```json
{ "success": false, "message": "...", "errors": {} }
```

Create an issue:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/issues \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Checkout fails",
    "description": "The checkout page shows an error after payment.",
    "priority": "high",
    "category": "bug"
  }'
```

List issues:

```bash
curl "http://127.0.0.1:8000/api/v1/issues"
curl "http://127.0.0.1:8000/api/v1/issues?status=open&priority=high"
curl "http://127.0.0.1:8000/api/v1/issues?status=in_progress&priority=medium&category=performance"
```

View one issue with comments:

```bash
curl http://127.0.0.1:8000/api/v1/issues/1
```

Update an issue:

```bash
curl -X PATCH http://127.0.0.1:8000/api/v1/issues/1 \
  -H "Content-Type: application/json" \
  -d '{"status": "in_progress"}'
```

Changing `description` resets `summary`, resets `summary_status` to `pending`, and dispatches a new summary job. Updating `status`, `title`, `priority`, or `category` alone does not regenerate the summary.

Add a comment:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/issues/1/comments \
  -H "Content-Type: application/json" \
  -d '{
    "author_name": "Dana",
    "body": "Please check the payment logs."
  }'
```

## Architecture Notes

- Laravel API only; authenticated using Laravel Sanctum for secure API access.
- SQLite is the default local database for a low-friction reviewer setup.
- Database queue is used locally; tests use the sync queue driver.
- Issues use Laravel soft deletes through `deleted_at`, but no delete endpoint is exposed.
- Single issue view eager loads comments to avoid N+1 queries.
- Comments are immutable and store only `created_at`.
- Every summary attempt is logged in `summary_generation_logs`.
- **Summary Log Audit API Endpoints**: The endpoints `GET /api/v1/summary-logs` and `GET /api/v1/issues/{id}/summary-logs` were added specifically to inspect and debug summary generation runs. These are optional audit/observability endpoints and can be removed if requested.
- The summary job retries up to three times and sets `summary_status = failed` if final failure handling runs.

The architecture is aligned with the requirements baseline: five versioned API endpoints (along with the optional summary log endpoints for reviewer auditing), SQLite/database queue defaults, and soft-delete foundation without delete/restore routes.

Additional project documentation:

- `ARCHITECTURE.md` — requirements-aligned architecture and decisions
- `CHANGELOG.md` — dated change history with the current state first
- `FEATURES.md` — feature inventory and endpoint list

Recommended public markdown set for repository push:

- `README.md`
- `ARCHITECTURE.md`
- `FEATURES.md`
- `CHANGELOG.md`

## Failed Jobs

Inspect failed jobs:

```bash
php artisan queue:failed
```

Retry a failed job:

```bash
php artisan queue:retry all
```

---

## AI Usage

**Claude** (chat) was used during the planning phase to assess the requirements, break them down by feature, and establish prioritization. This produced the feature list, architecture document, and project execution plan — including schema design and architectural decisions.

**OpenAI Codex** and **Antigravity CLI** were used for implementation and documentation, keeping the codebase and its documentation aligned throughout development.

All code in this repository is owned and explainable by the author. Nothing was submitted that cannot be walked through or reasoned about during review.
