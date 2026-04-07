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
- Prefer framework defaults unless a clear repo-specific need appears (this repo is close to stock Laravel 13).
- For model properties, follow existing Laravel 13 attribute style in `app/Models/User.php`.
- Keep frontend entrypoints at `resources/css/app.css` and `resources/js/app.js` so Vite config remains valid.
- Tailwind source scanning already includes Blade, JS, and compiled framework views (`resources/css/app.css` `@source` directives).

## Workflows (use these exact commands)
- Initial bootstrap: `composer run setup`
- Daily dev stack (server + queue listener + logs + Vite): `composer run dev`
- Tests (clears config first): `composer test`
- Fast route/config sanity checks:
  - `php artisan route:list`
  - `php artisan about`

## Testing Expectations
- Feature tests belong in `tests/Feature` (HTTP flows); unit tests in `tests/Unit`.
- If a change touches DB behavior, use migrations/factories and prefer Feature tests with DB assertions.

## AI Instruction Sources Found
- One glob scan was run for agent-instruction files.
- Relevant repo-level source found: `README.md` (includes "Agentic Development" note about Laravel Boost).
- Ignore `vendor/**/README.md` matches for project policy decisions.

