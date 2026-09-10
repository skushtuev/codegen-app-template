# AGENTS.md — backend

Rules for the backend. Repo-wide shared rules: **[../AGENTS.md](../AGENTS.md)** (read that first).
Architecture is governed by the bundled skill, which is the authority for the `Common\` module
and the HTTP layer: **[skills/yii3-backend-architecture/SKILL.md](skills/yii3-backend-architecture/SKILL.md)**
(+ `references/`). Console and tooling are not in the skill yet — this file remains the authority for those.

## Overview

A **Yii3** application (composer `yiisoft/app`, PHP 8.2–8.5) with three PSR-4 roots:

- `AdminApi\` → `admin-api/src` — the admin **HTTP API**: controllers, middleware, request/response
  DTOs, API services. Entry point `admin-api/web/index.php` (yii-runner-http).
- `Common\` → `common/src` — shared **domain + infrastructure**: `App/` (Active Record models,
  repositories, domain services), `Infra/` (Db, Cache, ObjectStorage, Config), `Shared/` (value
  objects, exceptions, HTTP helpers, Translator).
- `Console\` → `console/src` — **CLI** commands (`./yii`, yii-runner-console).

More HTTP apps may be added beside `admin-api` later (they reuse `Common\`).

## Stack — what we use and where it comes from

| Concern              | Source / library                                   | Where in the code                                                   |
| -------------------- | -------------------------------------------------- | ------------------------------------------------------------------ |
| Framework            | **Yii3** (`yiisoft/*`)                            | runners `yii-runner-http` / `yii-runner-console`                    |
| Routing              | `yiisoft/router` + `router-fastroute`             | `admin-api/config/routes.php` (`Group` / `Route`)                  |
| Middleware           | `yiisoft/middleware-dispatcher`                   | pipeline in `admin-api/config/di.php`                              |
| Input / hydration    | `yiisoft/input-http` + `request-body-parser` + hydrator | typed **Request DTOs** auto-hydrated; invalid → `400 {errors}` |
| JSON responses       | `yiisoft/data-response` (`JsonResponseFactory`)   | `Common\Shared\Http\ResponseFactory`                              |
| DI / config          | `yiisoft/di` + `definitions` + `yiisoft/config`   | config groups `di` / `params` / `routes` / `commands` / `i18n`     |
| ORM                  | `yiisoft/active-record`                            | `Common\App\Models\*` (extend `AbstractModel`)                    |
| Database             | **PostgreSQL** — `yiisoft/db-pgsql`               | `Common\Infra\Db\ConnectionFactory`                               |
| Migrations           | `yiisoft/db-migration`                            | `make yii migrate:*`                                              |
| Cache                | **Redis** — `yiisoft/cache-redis` (PSR-16)        | `Common\Infra\Cache\CacheFactory`                                |
| Auth                 | **JWT** (`firebase/php-jwt`) in an httpOnly cookie | `AdminApi\Service\AuthService` (cookie `adminJwt`)               |
| Permissions          | `Can` middleware + `AdminAccess`                  | `Can::withPermission(AdminAccess::…)` in routes                   |
| Object storage       | file / nop providers behind one interface         | `Common\Infra\ObjectStorage\ObjectStorageInterface`              |
| Value objects        | self-validating immutables                         | `Common\Shared\ValueObject\*` (`Email`, `Uuid`, `Text`, `NewPassword`, …) |
| i18n (backend)       | `Translator`                                       | `Common\Shared\Translator` + `*/config/i18n.php`                 |
| UUIDs                | `ramsey/uuid`                                      | `Common\Shared\Util\Uuid` + `ValueObject\Uuid`                  |
| Tests                | Codeception                                        | `make test`                                                       |
| Static analysis      | PHPStan + Psalm                                    | `make phpstan` / `make psalm`                                    |

## Layered architecture & enforced rules

**The flow:** `Controller / Console command → (validate + authorize) → Service → Repository → Active Record`.
Output via `ResponseFactory`; errors via exceptions.

1. **Entry point** (controller action or command) — thin, no business logic:
   - **Validate input first.** Typed **Request DTOs** are auto-hydrated as action params
     (`admin-api/src/Controller/<Domain>/Request/*Request.php`); **value objects** (`Email`, `Uuid`,
     `NewPassword`, …) guarantee well-formed values. Invalid input → `InputValidationException` →
     `400 {errors}` automatically. Route params via `#[RouteArgument]`.
   - **Authorize.** Controllers via route middleware: `Authenticate` (sets the user from the
     `adminJwt` cookie) + a guard (`MustAuthenticated`) + **`Can::withPermission(AdminAccess::X)`**.
     **Every protected endpoint declares its permission**; anon/public ones are explicit
     (`MustNotAuthenticated`, or `MustNotProduction` for `/docs`). Commands are CLI-trusted.
   - **Delegate** to a domain `Service`.
2. **Service** (`common/src/App/Service/<Domain>/Service.php`, extends `AbstractService`) — **one per
   domain, many methods**:
   - Holds all business logic + business validation (throw `ValidationException(messageKey, field:)`,
     e.g. `admin_user.email_already_exists`).
   - The **only** layer that calls **Repositories**.
   - Takes plain DTOs (`…/Data/<X>Dto`); returns models/DTOs (the controller maps them — never leak a
     raw model to the response).
   - Wraps multi-step writes in a transaction: `$this->getDb()->beginTransaction()`
     (`getDb()` = `ConnectionProvider::get()`).
   - On unexpected failures call `handleExceptionForApi()` (logs + throws `InternalException` → 500).
3. **Repository** (`common/src/App/Repository/<X>Repository.php`) — data access only, **called only
   from services**:
   - **Owns persistence:** `save($model)` (assigns UUID + timestamps, then `$model->save()` +
     `refresh()`). Never call `$model->save()` from a service/controller.
   - **The only place AR entities are created/loaded** (`getEmptyModel()`, `getOneById`,
     `getOneByEmail`, `getList`, `count`).
   - **Finder naming:** `getX()` returns **`null`** when absent; when a value is required, use a
     `must`-style throw (`MustException`) — see `AbstractController::mustUser()`.
4. **Models (Active Record)** (`common/src/App/Models/`, extend `AbstractModel`) — **dumb**: private
   typed props ↔ columns, `tableName()` (`{{%admin_users}}`), VO/enum get/set, at most trivial read
   helpers. **No business logic.** PK = **UUID** by default (string `id` from `Util\Uuid`); timestamps
   as `DateTimeImmutable`.
5. **Output** — never return the raw model: map it to a **Response DTO**
   (`admin-api/src/Controller/<Domain>/Response/*Response.php`, `::fromModel()`) and emit via
   `ResponseFactory` (`ok` 200 / `created` 201 / `noContent` 204 / `file`). Lists use
   `PaginationResponse::fromPagination()`.
6. **Errors** — throw, don't return error payloads. `ExceptionResponder` (`admin-api/config/di.php`)
   maps them:
   - `ValidationException` / `InputValidationException` → **`400 {errors: {field: message}}`** — the
     contract the frontend consumes; messages translated via `Translator`.
   - `NotAuthorizedException` → 401, `ForbiddenException` → 403, `NotFoundException` → 404,
     `InternalException` / `Throwable` → 500.
7. **Schema** — every change via a **migration** (`make yii migrate:create …`); never edit the DB by hand.
8. **Cross-cutting:** time in **UTC** (DB + API); **Redis cache only when explicitly needed**; **all
   file I/O via `ObjectStorageInterface`** (provider `file`/`nop` now, S3 later — never touch the FS
   directly); **API versioning** — none yet; **logging** — to be expanded later (services already log
   via `AbstractService`). **i18n is project-dependent** — the template is multilingual via
   `Translator`; a single-language project may pin one locale.

## HTTP layer (detail)

- **Pipeline** (`admin-api/config/di.php`): `ErrorCatcher` → `ExceptionResponder` →
  `RequestCatcherMiddleware` → `RequestBodyParser` → `Router`; fallback `NotFoundHandler`.
- **Routes** (`admin-api/config/routes.php`): nested `Group::create('/x')->middleware(...)->routes(
  Route::post('/y')->action([Controller::class, 'method']))`. Route args: `{id:[\w\-]+}` →
  `#[RouteArgument] string $id`.
- **Controllers**: `AdminApi\Controller\<Domain>\Controller extends AbstractController` (`final
  readonly`), per-action methods; base helpers `user()`, `mustUser()` (throws `MustException`),
  `baseUrl()`.
- **Pagination**: `PaginationRequest{page, perPage}` (`limit()`/`offset()`) ·
  `PaginationResponse{data, count, currentPage, perPage, pages}`.

## Middleware & auth (detail)

- `Authenticate` (top route group): reads the `adminJwt` **httpOnly cookie** → `AuthService` decodes
  the JWT (HS256, `ADMIN_AUTH_JWT_SECRET`) → loads the **not-banned** user → sets the `user` request
  attribute. Sliding refresh (TTL 7d, refresh when < 6d left). Does **not** block — guards do.
- **Guards:** `MustAuthenticated`, `MustNotAuthenticated`, `MustNotProduction` (e.g. `/docs`).
- **Authorization:** `Can::withPermission(AdminAccess::ADMIN_USERS | ADMIN_MEDIA)` →
  `AdminAccess::can($user, $permission)`.
- **Cookie:** httpOnly, `SameSite=Lax`, `Secure` off only in dev, domain = root domain in prod. Login
  is rate-limited via cache (max attempts / window in `AuthService`).

## Shared building blocks

- **Value objects** `Common\Shared\ValueObject\*` (`Email`, `Uuid`, `Text`, `NullableText`,
  `NewPassword`): self-validating + immutable; used in model get/set and service inputs.
- **Exceptions** — two namespaces:
  - domain `Common\Shared\Exception\*`: `ValidationException` (`messageKey` + `field` + params,
    implements `TranslatableException`), `TranslatableException` (interface), `MustException`,
    `UnsupportedException`;
  - HTTP `Common\Shared\Http\Exception\*`: `NotFoundException`, `ForbiddenException`,
    `NotAuthorizedException`, `InternalException`.
- **HTTP helpers**: `ResponseFactory`, `PaginationRequest` / `PaginationResponse`.
- **Translator**: translates `messageKey`s via `@common/config/i18n.php` + `@adminApi/config/i18n.php`;
  locale resolved from the request.

## Infra (`Common\Infra`)

- **Db**: `ConnectionFactory` (PostgreSQL); access the active connection via `ConnectionProvider::get()`
  (services use `$this->getDb()`).
- **Cache**: `CacheFactory` (Redis, PSR-16 `CacheInterface`).
- **ObjectStorage**: `ObjectStorageInterface` + `FileObjectStorage` / `NopObjectStorage` +
  `ObjectStorageFactory`, selected by `OBJECT_STORAGE_PROVIDER` (`file` | `nop`).
- **Config**: `Common\Infra\Config\Config` (`mustString` / `string` / `isDev`) over env vars;
  `EnvironmentEnum` (dev / prod / test).

## Layout & config

- `yiisoft/config`; config-plugin file `common/config/configuration.php`. Config **groups** merge
  across `common/config` + each app's `config/`: `di.php`, `params.php`, `routes.php` (admin-api),
  `commands.php` (console), `i18n.php`. Environment params in
  `common/config/environments/{dev,prod,test}/params.php`.
- **Register a service** in the app's `config/di.php` (or `common/config/di.php` if shared); add
  settings to `params.php`.

## Console

- Commands: `Console\Commands\<Domain>\<X>Command`, registered in `console/config/commands.php`
  (`'name' => Class::class`, e.g. `'admin-users:create' => CreateCommand::class`). Run with
  `make yii <name>` (e.g. `make yii admin-users:create`).
- **Migrations** (`yiisoft/db-migration`): `make yii migrate:create <name>`, `make yii migrate:up`,
  `make yii migrate:down` (run `make yii list` for the exact set).

## Commands & quality gates

From `backend/` (Makefile wraps docker compose):

- `make up` / `down` / `stop` / `clear` / `build` / `shell`
- `make composer <args>` · `make yii <cmd>`
- `make test` / `test-coverage` / `codecept`
- `make phpstan` / `psalm` / `rector` / `cs-fix`

**Before merging to `main` (repo rule):** `make phpstan` must pass.

## Env

Copy `.env.example` → `.env`. Keys: `ENVIRONMENT` (dev/prod/test); `DB_*`; `REDIS_*`;
`OBJECT_STORAGE_PROVIDER` (`file`|`nop`) / `OBJECT_STORAGE_ROOT_PATH` / `PUBLIC_UPLOADS_BASE_URL`;
`ADMIN_AUTH_JWT_SECRET` (set a real random 32-byte secret).

## Naming

- PSR-4 roots `AdminApi\` / `Common\` / `Console\`. Controllers `…\Controller`; domain services
  `Common\App\Service\<Domain>\Service`; repositories `…Repository`; Request/Response DTOs under the
  controller's `Request/` / `Response/`; service input DTOs under `…\Data\`.
- DB columns `snake_case`; tables `{{%plural_snake_case}}`. Interfaces/value objects in PascalCase.
