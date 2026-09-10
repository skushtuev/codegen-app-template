# The HTTP Layer

One HTTP app over `Common\`: `AdminApi\`. A second API (client-facing) will sit beside it and reuse the same `Common\`, so nothing here may be written in a way that assumes there is only one.

This file describes **structure and code style**. Which authentication mechanism is used, what a permission actually checks, how long a session lasts — those are specific realisations, not architecture, and are deliberately not written here.

## 1. The Request Path

```txt
Route + group middleware
  -> identify        sets the current user if there is one, never blocks
  -> guard           blocks when the caller is not allowed to be here
  -> authorize       blocks when the caller lacks the permission
    -> Controller    validate (Request DTO) -> delegate (Service) -> map (Response DTO)
      -> Service     business logic
```

Errors are thrown, never returned. A central exception responder maps them to status codes and bodies.

## 2. Routes

Routes are **REST**: the URL names the *resource*, the HTTP method names the *action*.

| Action | Route |
| --- | --- |
| list | `GET /articles` |
| create | `POST /articles` |
| read one | `GET /articles/{id}` |
| update | `PATCH /articles/{id}` |
| delete | `DELETE /articles/{id}` |

**Non-CRUD actions use `PATCH` with a body**, not a verb in the path. Banning a user, changing a role and resetting a password are all partial updates of the same resource:

```txt
PATCH /admin-users/{id}   {"banned": true}
PATCH /admin-users/{id}   {"role": "admin"}
```

Never `POST /admin-users/ban/{id}`.

Structure of `admin-api/config/routes.php`:

```php
const ROUTE_ID = '{id:[\w\-]+}';

Group::create('')->middleware(Identify::class)->routes(
    Group::create('/articles')
        ->middleware(Guard::class, Can::withPermission(Access::ARTICLES))
        ->routes(
            Route::get('')->action([ArticlesController::class, 'list']),
            Route::post('')->action([ArticlesController::class, 'create']),
            Route::patch('/' . ROUTE_ID)->action([ArticlesController::class, 'change']),
            Route::delete('/' . ROUTE_ID)->action([ArticlesController::class, 'delete']),
        ),
);
```

- **Middleware is attached to the group, not to each route**, unless one route genuinely differs.
- Groups nest and inherit the parent's middleware.
- Reuse the shared `ROUTE_ID` constant; do not inline id patterns.
- **Every protected group declares its permission.** Public or anonymous routes say so explicitly with their own guard — access is never implicit.
- Controllers are imported **aliased**, because every controller class is named `Controller`:
  `use AdminApi\Controller\Articles\Controller as ArticlesController;`

### PATCH request DTOs

`PATCH` means *partial* update: apply only the fields that were sent, leave the rest alone.

The hydrator does no type casting and every property defaults to `null`, so **a field that was omitted and a field explicitly sent as `null` look identical**. Where that difference matters — a nullable column the caller may want to clear — the DTO needs an explicit signal (a separate flag, or checking the raw parsed body) rather than relying on `null`.

## 3. Middleware

Three roles, and each does only its own job:

| Role | Responsibility | On failure |
| --- | --- | --- |
| **Identify** | resolve the current user from the request and attach it | nothing — passes through |
| **Guard** | require a caller state (authenticated / not authenticated / not production) | throws |
| **Authorize** | require a permission for the resolved user | throws |

```php
final readonly class Guard implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!Identify::user($request)) {
            throw new NotAuthorizedException();
        }

        return $handler->handle($request);
    }
}
```

Rules:

- **The identifying middleware never blocks.** It sits on the root group so any route can see the current user; deciding what absence means belongs to guards. This is what lets a public endpoint behave differently for a signed-in caller without a second code path.
- **One way in.** The current user is read through a single static accessor on the identifying middleware, never from the request attribute directly and never by re-reading credentials.
- The permission middleware is configured per route with a static factory returning a DI definition, so it can be written inline in `routes.php`:
  `Can::withPermission(Access::ARTICLES)`.
- Permissions are constants on one class per API, with the check in one place. Adding a permission means adding the constant, the check, and the entry in whatever the API exposes to the client — miss the last one and the UI never learns it has the capability.

## 4. Controllers

**One controller class per domain**, always named `Controller`, `final readonly`, extending the app's `AbstractController`. One public method per route action.

```php
namespace AdminApi\Controller\Articles;

final readonly class Controller extends AbstractController
{
    public function __construct(
        private ResponseFactory $responseFactory,
        private ArticleService $articleService,
    ) {}

    public function create(CreateRequest $input): ResponseInterface
    {
        return $this->responseFactory->created(
            ArticleResponse::fromModel($this->articleService->create(new CreateDto(
                title: $input->title(),
                body: $input->body(),
            ))),
        );
    }
}
```

A controller does exactly four things:

1. **Take a Request DTO** — it arrives already hydrated and validated.
2. **Turn route arguments into value objects**, so a malformed id is a field error rather than a crash: `new Uuid($id, field: 'id')`.
3. **Delegate to a service.** A controller may use more than one service when an endpoint genuinely spans domains, but it never contains business logic itself.
4. **Map the result** to a Response DTO and emit it through the response factory.

It holds no logic beyond that. No repositories, no queries, no `save()`, no connection.

### Turning `null` into a status

`Common\` returns `null` for a missing entity. **The controller is where that becomes HTTP:**

```php
if (!$this->articleService->delete($uuid)) {
    throw new NotFoundException();
}

return $this->responseFactory->noContent();
```

### Per-action rules

Checks that need the request live as private methods on the controller — for example refusing an action a caller aims at their own account:

```php
private function mustNotSelf(ServerRequestInterface $request, Uuid $compare)
{
    if ($this->mustUser($request)->getId()->equals($compare)) {
        throw new ForbiddenException();
    }
}
```

Order matters: build the value object first, then authorize, then call the service — a malformed id should fail before any authorization work.

## 5. Request DTOs and Rules

`AdminApi\Controller\<Domain>\Request\<X>Request`, `final class`, marked `#[FromBody]` or `#[FromQuery]`, hydrated automatically as an action parameter.

```php
#[FromBody]
final class CreateRequest extends AbstractInput
{
    public function __construct(
        #[Required]
        #[ValueObject(Email::class)]
        private readonly mixed $email = null,
        #[StringValue(skipOnEmpty: true)]
        private readonly mixed $lastName = null,
    ) {}

    public function email(): Email
    {
        return new Email((string) $this->email, field: 'email');
    }
}
```

Code style, all of it load-bearing:

- `final class`, **not** readonly — the hydrator writes the properties.
- Every property is `private readonly mixed` with a `= null` default. **The hydrator does no type casting**, so a typed property would not be filled.
- Every property has an **accessor method** named after it that returns the real type and casts explicitly.
- Secrets carry `#[SensitiveParameter]` so they are stripped from stack traces.
- Optional fields drop `#[Required]` and use `skipOnEmpty: true`.

### The two stages

| Stage | Where | Reports |
| --- | --- | --- |
| **Rules** | attributes, during hydration | **all** failing fields at once |
| **Value objects** | the accessor methods | **one** field, then stops |

Rules check *shape* — present, is a string, within range. Value objects guarantee *meaning* and are what the accessor hands to the controller. **Rules exist only at the HTTP edge**; nothing in `App\` uses them.

Rules live in `Common\Shared\Http\Rule\` because every API needs `Required`, `StringValue`, `Integer`, `ValueObject`. An API may add its own rules for constraints that encode only its business — those live in that app's namespace.

Rules are thin wrappers over the framework's validator rules, substituting the project's message keys as defaults. **Never use the framework rules directly**, or the message will not translate. A new rule means new message keys in both locales.

Enums have no rule — validate in the accessor and throw `ValidationException` with the not-in-list key.

## 6. Response DTOs

`AdminApi\Controller\<Domain>\Response\<X>Response` — a plain `final class` with public promoted scalars and a static factory.

```php
final class ArticleResponse
{
    public function __construct(
        public string $id,
        public string $title,
        public string $createdAt,
    ) {}

    public static function fromModel(Article $model): self
    {
        return new self(
            id: $model->getId()->value(),
            title: $model->getTitle()->value(),
            createdAt: $model->getCreatedAt()->format(DateTimeInterface::ATOM),
        );
    }
}
```

- **Never return a model from an endpoint.** Always map it.
- Scalars only — unwrap value objects with `->value()`, enums with `->value`.
- Dates always ISO-8601 (`DateTimeInterface::ATOM`), in UTC.
- Factories are `fromModel()` for models, `fromEnum()` for enums.
- Serialization is over public properties, so nothing is hidden — never add a property you do not want in the API.

The response factory gives `ok()` 200, `created()` 201, `noContent()` 204, and streamed file responses. Prefer the streaming variant with an explicit content type over any helper that buffers a whole body.

## 7. The Error Contract

**This is architecture — every API in the project produces the same shape.** The frontend maps it straight onto form fields, so it does not vary per endpoint or per app.

A failed request returns **400** with a single `errors` key holding a flat map of field name to translated message. Nothing else.

| Thrown | Status |
| --- | --- |
| input validation (rules) | 400, every failing field |
| `ValidationException` | 400, one field |
| not authorized | 401 |
| forbidden | 403 |
| not found | 404 |
| anything else | 500 |

- A `ValidationException` carrying no field lands under a generic key, which the client renders as a form-level error. **Pass `field:` whenever the error belongs to an input.**
- Only the first error per field is reported, so a field failing several rules yields one message.
- HTTP exceptions are empty marker classes — the status code is the message.
- **Throw from controllers and middleware only.** A service that throws `NotFoundException` couples the domain to HTTP.

### Message keys at the edge

Domain code throws a **key**, never a sentence. Translation happens here, at the boundary, using the locale resolved from the request, falling back to a default. Keys are dot-namespaced (`validation.*`, `admin_user.*`) and must exist in **both** locales — an unknown key is returned verbatim, so a missing translation ships the raw key to the UI with no error anywhere.

## 8. Pagination

List endpoints take page and size as query parameters and return a fixed envelope: the rows plus total count, current page, page size and page count.

```php
$pagination = $this->articleService->getList(new PaginationRequest(
    page: $input->page(),
    perPage: $input->perPage(),
));

return $this->responseFactory->ok(new PaginationResponse(
    data: array_map(ArticleResponse::fromModel(...), $pagination->data),
    count: $pagination->count,
    currentPage: $pagination->currentPage,
    perPage: $pagination->perPage,
    pages: $pagination->pages,
));
```

The service returns the envelope holding **models**; the controller rebuilds it with mapped DTOs, because the envelope is immutable.

`PaginationRequest` rejects a page or size below 1 by throwing — that is a programmer error, a 500. **The request DTO's rules must enforce the bounds first** (`#[Integer(min: 1)]`, and an upper bound on page size) so a bad query string is a 400.

## 9. App Services

`AdminApi\Service\` holds services that belong to **this API only** — the ones that fail the "would a second API need this?" test.

Authentication is the example: how this API establishes who the caller is, is its own concern, and that service grows with it. Adding a captcha to admin login means extending that service, not `Common\`.

Same shape as a domain service — `final readonly`, constructor injection, one class per concern. The difference is only where it lives and what it is allowed to know: an app service may use HTTP types, because it is part of the app.

When a second API needs the same behaviour, the reusable part moves down into `Common\` and each app keeps its own specifics on top.

## 10. Known Deviations

Places where the current code does not match the rules above. Treat the rule as correct.

- **`admin-users` routes are not REST** — `POST /admin-users/create`, `POST /admin-users/ban/{id}`, `POST /admin-users/role/{id}`, `GET /admin-users/list` (section 2). Under the rule these are `POST /admin-users`, `PATCH /admin-users/{id}`, `GET /admin-users`. Not migrated: it would touch the routes, the controller signatures and the frontend API layer.
- **The identifying middleware is applied twice** on one group — once on the root group and again on `/account`. Harmless but redundant; do not copy it.
