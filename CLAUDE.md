# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel 10 (PHP ^7.3||^8.0) REST API backend for "IE Module" / SkillMatrix — an industrial engineering / employee management system. Auth is via JWT (`php-open-source-saver/jwt-auth`), not Sanctum despite it being installed (`config/auth.php`: `api` guard driver is `jwt`). Laravel Passport was removed in favor of JWT — see "Auth flow" below.

## Commands

```bash
# Install PHP deps
composer install

# Run the app (default Laravel dev server)
php artisan serve

# Run all tests
php artisan test
# or directly:
vendor/bin/phpunit

# Run a single test file / method
vendor/bin/phpunit tests/Feature/SomeTest.php
vendor/bin/phpunit --filter testMethodName

# Migrations
php artisan migrate
php artisan migrate:fresh

# Lint/format (Laravel Pint, preset "laravel")
vendor/bin/pint
vendor/bin/pint --test   # check only, no changes

# Frontend asset build (Laravel Mix — only for the minimal resources/ assets, not a SPA)
npm run dev
npm run watch
npm run prod
```

There are no `database/seeders` directory and `phpunit.xml` runs against an in-memory SQLite DB (`DB_CONNECTION=sqlite`, `:memory:`) for tests, separate from the MySQL `ie_module` DB used in dev/prod.

## Architecture

### Models live in `app/`, not `app/Models/`

Eloquent models (`Employee`, `User`, `Role`, `Permission`, `Screen`, `Department`, `Designation`, `EmployeeCategory`, `ProductionLine`, `LineCategory`, `ForeignKeyMapper`, `HashStore`, `AuditLog`) sit directly under the `App\` namespace at the project root of `app/`. Repositories/controllers reference them as `App\Employee`, `App\User`, etc. — including dynamically (`"App\\" . $obj`) in the generic search code, so renaming a model breaks that reflection-based lookup.

### Layering: Controller → Repository → Validator → Model

This codebase follows a consistent, repetitive pattern across most entities (Employee, Department, Designation, EmployeeCategory, LineCategory, ProductionLine, ForeignKeyMapper, Permission, Role, Screen, User):

- **Controllers** (`app/Http/Controllers/Api/*Controller.php`) are thin — they pull request data and delegate to a matching `*Repository`, wrapping calls in `DB::beginTransaction()/commit()/rollBack()` and returning a uniform JSON envelope: `{"status": "success"|"error", "data": ..., "message": ...}`.
- **Repositories** (`app/Http/Repositories/*Repository.php`) hold the actual business logic as static methods, typically `createRec`, `updateRec`, `createMultipleRecs`, `updateMultipleRecs`, `deleteRecs`. `updateRec` enforces optimistic concurrency by comparing the model's `updated_at` against the `updated_at` sent by the client, throwing `ConcurrencyCheckFailedException` on mismatch.
- **Validators** are split into three classes per entity under `app/Http/Validators/`: `<Entity>CommonValidator` (rules shared by create/update), `<Entity>CreateValidator` (adds `unique` rules), `<Entity>UpdateValidator` (adds uniqueness rules scoped to exclude the current record's id). When adding a new entity, follow this exact three-file split rather than inlining validation in the controller.
- **`Utilities`** (`app/Http/Repositories/Utilities.php`) is a static grab-bag used everywhere: generic `destroy()` (bulk/partial delete with per-row error capture), `hydrate()` (fills missing fields on update from the existing model so partial payloads don't null out columns), Excel import helpers (`readFile`, `discardColumns`, `prepareHeader`, `prepareForeignKeyValues`), JSON-size-map helpers (`sortQtyJson`, `json_compare`, `json_subtract`, `json_numerize` — for garment-size-style `qty_json` columns), and `extractError()` which turns the first Laravel validator error into a thrown `GeneralException`.
- **Exceptions** (`app/Exceptions/`): `GeneralException` and `ConcurrencyCheckFailedException` are the main custom ones; thrown deep in repositories and expected to bubble up to a JSON error response. `NoModificationsAllowedException` blocks edits to records with a terminal `status` (e.g. "Closed") — see the `Utilities::destroy()` entity check for `Fpo`/`Fppo`/`JobCard` (these entities aren't all present in this trimmed repo, so treat that block as legacy/cross-module logic).

### Generic, schema-driven search (no per-entity query endpoints)

Rather than writing bespoke list/filter endpoints per entity, this app exposes a few generic, JSON-driven search endpoints in `SearchController` (`app/Http/Controllers/Api/SearchController.php`):

- `POST novelSearch` — the primary one used by the frontend. Takes a nested JSON body keyed by model name (e.g. `{"Employee": {"first_name": "...", "relations": [...], "orderby": "...", "limit": ...}}`), recursively resolves `relations` into `whereIn(id, ...)` subqueries (`_getModelIds`), then returns results through a `App\Http\Resources\<Model>WithParentsResource`. Every searchable model needs a corresponding `*WithParentsResource` class in `app/Http/Resources/` or `novelSearch` will fail to resolve the resource class.
- `searchByParameters` / `searchByParametersJson` / `searchByUuid` — older/alternate search paths using a `{where: [...], wherein: [...], select, relations, distinct, orderby, limit}` shape per model; `searchByUuid` resolves a previously stored search payload from the `HashStore` model (`hashStores` table — `POST hashStores` stores a payload, returns a uuid; `GET hashStores/getByUuid/{uuid}` resolves it). This is used to give a short shareable key for a complex saved search/filter instead of a long query string.

When adding a new model that should be searchable via `novelSearch`, you must add a `<Model>WithParentsResource` class, not just the model and migration.

### Permissions are screen-based, not just role-based

`PermissionController`/`PermissionRepository` implement a navigator/screen permission model: `Screen` rows define UI sections, `Permission` rows link `Role` + `Screen` with allowed actions, and `isAuthorized`, `getPermissions`, `getNavigator`, `updatePermissions` drive both route-level authorization and frontend menu rendering. Check `PermissionRepository` before changing how a role's access to a screen is computed.

### Auth flow

`AuthController@login` supports two paths: normal email/password (`Auth::attempt`), and a "common user" SSO-style path where `common_user` is an encrypted token (`Crypt::decrypt`) standing in for a password — used for cross-system handoff logins. Both paths reject login for users where `is_active` is false, then issue a JWT access token (`JWTAuth::fromUser`, 15 min TTL via `JWT_TTL`, returned in the JSON body) plus an opaque refresh token (`App\Http\Repositories\RefreshTokenRepository`, 7 day TTL via `JWT_REFRESH_TOKEN_TTL`/`config/refresh_token.php`). The refresh token is a random string stored in Redis (`refresh_token:<token>` → user id), not a second JWT, so it can be revoked on logout/rotation — unlike the stateless access token, which is only invalidated via jwt-auth's blacklist (`JWTAuth::invalidate`, requires `CACHE_DRIVER=redis`). `App\User` implements `JWTSubject` instead of Passport's `HasApiTokens`.

The refresh token never appears in the JSON body — it's set as an httpOnly, `Secure`, `SameSite=Lax` cookie (`refresh_token`, scoped to path `/api/v1`) so XSS can't read it. A companion non-httpOnly cookie (`csrf_refresh_token`, excluded from encryption in `App\Http\Middleware\EncryptCookies::$except`) implements a double-submit CSRF check: the frontend must read that cookie via JS and echo its value back as an `X-CSRF-TOKEN` header on any request that touches the refresh cookie. `App\Http\Middleware\VerifyCsrfCookie` (aliased `csrf.cookie` in `Kernel::$routeMiddleware`) enforces this on `POST refreshToken` (outside `auth:api`, since the access token may already be expired) and `POST logout` (inside `auth:api`) — both rotate/clear the cookie pair. The `api` middleware group had to gain `EncryptCookies` + `AddQueuedCookiesToResponse` for any of this to work, since the stock group only had `throttle` + `SubstituteBindings`. CORS (`config/cors.php`) now requires `supports_credentials: true` and an explicit `FRONTEND_URLS` env allowlist — browsers reject `*` once credentials/cookies are involved.

### Excel import/export

`maatwebsite/excel` is used for both directions:
- Export: `app/Exports/EmployeesExport.php` + `EmployeeController@export` (`GET employees/export`).
- Import: generic `Utilities::readFile`/`prepareHeader`/`prepareForeignKeyValues` helpers support mapping spreadsheet columns to DB columns, including resolving foreign keys by a lookup column (e.g. mapping a department *name* in the sheet to a `department_id`) via `Utilities::getForeignKeyValue`.

### Routes

All API routes are versioned under `/api/v1` (`routes/api.php`) and registered as string controller actions (`'Api\EmployeeController@show'`), not invokable/class-based route binding except `MasterDetailController` (registered as `'Api\MasterDetailController'`, an invokable controller). Nearly everything except `login`, `register`, `refreshToken`, and `fpos/{fpo}/generateLayout` sits behind the `auth:api` (JWT) middleware group.

### Repo-specific gotchas

- `QueriesController::getJobCardData()` references `App\GetJobCardData`, a model that does not exist in this trimmed-down repo — that method (and likely the `Fpo`/`Fppo`/`JobCard` references in `Utilities::destroy`) are leftovers from a larger sibling module; don't assume they work without first checking whether the corresponding model/table exists.
- `routes/api.php` also references `ImportExportController` and `FpoController` which are not present under `app/Http/Controllers/Api/` in this repo — routes to those will 500 until those controllers are added back or the routes are removed.
- `systemdata.json` at the repo root contains plaintext credentials (SMTP password) — treat it as a secret file, do not read its contents into logs/output, and flag if asked to commit changes near it.
