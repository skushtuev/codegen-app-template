---
name: yii3-backend-architecture
description: Use when working on the Yii3 backend - the Common module (App layer with Active Record models, repositories and domain services; Infra with db, migrations, cache and object storage; Shared with value objects and helpers) and the HTTP layer (routes, middleware, controllers, request/response DTOs, validation rules, app-specific services, error contract). Covers typed service parameters, value objects instead of validation, enums, and transactions.
---

# Yii3 Backend Architecture (backend)

## Scope

This skill describes **architecture and code style**, not implementation choices. How the request path is layered is architecture; that today's session happens to use a JWT in a cookie is a specific realisation and is not written here.

Covered so far: the **`Common\` module** and the **HTTP layer**. Console (`Console\`) and tooling are not described yet — `backend/AGENTS.md` remains the authority for those.

## 1. The Layering

One direction, one job per layer:

```txt
Route (+ middleware) -> Controller -> Service -> Repository -> Active Record -> DB
                            |            |
                     Request DTO    Data DTO (…\Data\*Dto)
                            |
                     Response DTO -> ResponseFactory -> JSON
```

- **Controllers are thin** — validate, authorize, delegate, map the result. No business logic.
- **Services own business logic** and are the only layer that calls repositories.
- **Repositories own data access** and are the only place a model is created or queried.
- **Models are dumb** — columns in, value objects out.
- **Never return a model from an endpoint.** Map it to a Response DTO.

## 2. Common vs App

`Common\` is the shared core. Today the repo ships one HTTP app (`admin-api`), but a real project has at least two — an admin API and a client API — and both reuse `Common\`.

The test for every new class:

> **Would a second API need this too?**
> Yes -> `Common\` · No, it belongs to this one API -> that app's namespace

That is why `AuthService` and `AdminAccess` live in `AdminApi\Service\`: they are *this* API's concerns. `AuthService` is responsible for authentication in admin-api and grows with it — add a captcha tomorrow and it goes there.

The same applies to validation rules: the common rules live in `Common\Shared\Http\Rule\`, and an API may add rules that encode only its own business.

## 3. Layout

```txt
common/src/          Common\    reusable by any API
  App/                            business logic — Models, Repository, Service
  Infra/                          db + migrations, cache, object storage, config
  Shared/                         no business logic — value objects, helpers, http helpers
admin-api/src/       AdminApi\  one HTTP app over Common
  Controller/                     controllers + Request/ + Response/ DTOs
  Middleware/                     identify, guard, authorize
  Service/                        this API's specific services
console/src/         Console\    CLI entry points (not yet described)
```

## 4. The Common Module

`App\` holds all business logic, `Infra\` wraps every external dependency, `Shared\` holds what several domains need and contains no business logic.

Key rules: all data access goes through a repository · services take typed parameters only · a service returns `null` for a missing entity, never an HTTP exception · the service owns transactions.

-> **`references/common.md`**

## 5. The HTTP Layer

Routes are REST. Middleware identifies, guards block, a permission middleware authorizes. Controllers are one class per domain and stay thin. Request DTOs validate the edge and hand back value objects; Response DTOs shape the output.

-> **`references/http.md`**

## 6. Rules That Apply Everywhere

- **Typed parameters, always.** Value objects replace validation — a badly-shaped value cannot be constructed, so there is nothing to validate later.
- **Every enum lives in an `Enum\` namespace.** A fixed set of values is always an enum, never a bare string or int.
- **Data objects are immutable** — `final readonly`, public typed properties, no behaviour.
- **`Common\` never knows HTTP.** No status codes, no requests, no responses below the app layer.
- **`declare(strict_types=1);` everywhere**, and classes are `final` (`final readonly` for controllers, services, repositories, DTOs and value objects). Models are `final` but not readonly — Active Record mutates them.

## 7. Naming

- Services `App\Service\<Domain>\Service` · repositories `App\Repository\<X>Repository` · service inputs `App\Service\<Domain>\Data\<X>Dto`.
- Controllers `AdminApi\Controller\<Domain>\Controller` — one class per domain, always named `Controller`, so imports are aliased.
- Request/Response DTOs under the controller's `Request/` and `Response/`, suffixed `Request` / `Response`.
- Enums in an `Enum\` namespace. Value objects in `Shared\ValueObject\`.
- DB columns and model properties `snake_case`; tables `{{%plural_snake_case}}`. Everything else `camelCase` / `PascalCase`.
- Message keys `dot.snake_case` (`admin_user.email_already_exists`).

## Reference files

- `references/common.md`
- `references/http.md`
