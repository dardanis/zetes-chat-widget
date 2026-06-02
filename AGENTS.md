# AGENTS Guide

## Snapshot
- Project is a fresh Laravel 13 app (`laravel/framework:^13.0`) on PHP 8.3+; no domain modules yet.
- Runtime defaults are database-backed infra: sessions, cache, and queue use DB tables (`.env`, `database/migrations/0001_01_01_00000{0,1,2}_*.php`).
- Frontend is Vite + Tailwind v4 with Blade (`package.json`, `vite.config.js`, `resources/views/welcome.blade.php`).

## Read First (high signal)
- `bootstrap/app.php`: app wiring (`web.php`, `console.php`, health endpoint `/up`).
- `routes/web.php`: current HTTP surface (only `/` -> `welcome` view).
- `app/Models/User.php`: Laravel 13 style attribute metadata via PHP attributes (`#[Fillable]`, `#[Hidden]`) plus `casts()`.
- `composer.json`: canonical dev workflows (`setup`, `dev`, `test`) and tooling (`pint`, `pail`).
- `phpunit.xml`: test env uses in-memory SQLite and array drivers.

## Architecture and Data Flow
- Request flow is standard Laravel: route -> controller/closure -> Blade/Eloquent/service layer.
- Current route is closure-based; add controllers under `app/Http/Controllers` once routes grow.
- Persistence baseline is SQLite (`database/database.sqlite` in local env) with migration-first schema changes.
- Queue/cache/session persistence is DB-backed by default, so schema migrations are required before using those features.

## Project Conventions to Follow

### Model Patterns
- **Relationships**: Use `BelongsTo`, `HasMany`, `BelongsToMany` with `withPivot()` and `withTimestamps()` for junction tables (e.g., tenant-user membership with role).
- **Fillable/Hidden**: Use traditional `$fillable`/`$hidden` (not PHP 8.x attributes) for broad compatibility.
- **Casts**: Use `protected function casts()` method for type safety (e.g., JSON metadata as `array`).
- **Scoping**: Always include `tenant_id` and `project_id` in queries that touch sensitive data; use model scopes or explicit WHERE clauses.

### RAG Service Layer
- Services live under `app/Services/Rag/` and encapsulate domain logic (chunking, retrieval, embedding, generation, web crawling).
- Each service is responsible for a single concern (e.g., `DocumentChunkingService` handles text chunking; `OllamaEmbeddingService` calls the Ollama API).
- Services receive configuration from `config/rag.php` via dependency injection or direct config access.
- Errors in services (e.g., Ollama timeouts) should be caught and gracefully degraded or re-queued.

### Controller/Endpoint Patterns
- **Authenticated Controllers** (TenantController, ProjectController, etc.): Check user's tenant membership via `$user->tenants()` before returning tenant data.
- **Public Widget Controllers** (WidgetChatController): Use middleware (`widget.request`) to validate secret + origin; do not require user authentication.
- **Closure vs. Class-based**: Prefer class-based controllers under `app/Http/Controllers/` for clarity and testability.

### Job Patterns
- Queued jobs (ProcessProjectDocumentJob, EmbedDocumentChunkJob, CrawlProjectWebsiteJob) should:
  - Accept minimal data in constructor (IDs, not full models, to avoid serialization issues).
  - Retrieve models in `handle()` to ensure fresh state.
  - Include explicit error handling (fail with backoff or DLQ strategy).
  - Log progress and failures for debugging.

### Middleware
- `ValidateWidgetRequest.php`: Confirms widget secret hash matches, origin is allowlisted, and session token is valid.
- Apply widget middleware only to public endpoints; use standard `auth:sanctum` for authenticated routes.

### Frontend Conventions
- **Main App**: Vite entrypoints are `resources/css/app.css` and `resources/js/app.js`; Tailwind config includes Blade, JS, and framework view paths.
- **Admin UI**: `frontend/` directory contains Angular app with separate TypeScript config; build outputs go to `public/ng/`.
- **Embeddable Widget**: `public/widget/` contains the compiled chat widget (built separately, likely from `frontend/` sources); embed via script tag with `widgetKey` and `X-Widget-Secret`.

### Priority File Edits
- Model changes: update migration + model + factory (if applicable).
- API changes: update controller + route + tests.
- RAG logic: update service + jobs + events as needed.
- Widget security: test both with correct and incorrect secrets/origins in feature tests.

## Workflows (use these exact commands)
- Initial bootstrap: `composer run setup` (installs deps, generates key, runs migrations, builds frontend).
- Daily dev stack (server + queue listener + logs + Vite): `composer run dev` (runs concurrently; watch for job failures in the logs pane).
- Tests (clears config first): `composer test`.
- Fast route/config sanity checks:
  - `php artisan route:list` (shows all API routes).
  - `php artisan about` (shows environment + config summary).
- RAG-specific checks:
  - Verify Ollama is running on `OLLAMA_BASE_URL` (default `http://localhost:11434`).
  - Check `.env` for RAG_* variables (chunking, retrieval, crawler, widget settings).
  - Monitor queue with `php artisan queue:listen` to see ingestion/embedding jobs in real time.

## Testing Expectations
- Feature tests belong in `tests/Feature` (HTTP flows); unit tests in `tests/Unit`.
- If a change touches DB behavior, use migrations/factories and prefer Feature tests with DB assertions.

## AI Instruction Sources Found
- One glob scan was run for agent-instruction files.
- Relevant repo-level source found: `README.md` (includes "Agentic Development" note about Laravel Boost).
- Ignore `vendor/**/README.md` matches for project policy decisions.

