# The Common Module

`Common\` is the shared core, reusable by any API. Three roots, three jobs:

```txt
App/      the application layer — all business logic
Infra/    infrastructure — db + migrations, cache, object storage, config
Shared/   no business logic — value objects, helpers, immutable objects, http helpers
```

Nothing else belongs at the top of `Common\`.

## 1. App — Models

Active Record entities in `Common\App\Models\`, extending `AbstractModel`.

```php
final class AdminUser extends AbstractModel
{
    use PrivatePropertiesTrait;

    private string $email;
    private ?DateTimeImmutable $created_at = null;

    public function tableName(): string { return '{{%admin_users}}'; }

    public function getEmail(): Email { return new Email($this->email); }
    public function setEmail(Email $email): void { $this->email = $email->value(); }
}
```

- Models are **dumb**: private typed properties matching the columns, `tableName()`, value-object get/set pairs. No business logic, no queries, no `save()`.
- Properties are `private` and **`snake_case`**, named exactly like the DB columns — the only place in the codebase that is not `camelCase`.
- The primary key is a UUID string exposed as a `Uuid` value object.
- **Never construct or query a model outside a repository.**
- Constructing a value object inside a model takes no `field:` argument — a bad stored value is a bug, not user error.

Timestamps that are non-null in the DB but filled by the repository are declared nullable and throw in the getter. Reaching that throw means the repository was bypassed:

```php
public function getCreatedAt(): DateTimeImmutable
{
    return $this->created_at ?? throw new LogicException('Admin user createdAt is not set.');
}
```

## 2. App — Repositories

**All data access goes through a repository.** No service, command or controller queries a model, constructs one, or calls `save()` on it. The repository is the only door to the data.

`Common\App\Repository\<X>Repository`, `final readonly`, with the model injected as a query prototype:

```php
final readonly class AdminUserRepository
{
    public function __construct(
        private AdminUser $model,
        private UuidUtil $uuid,
    ) {}

    public function getEmptyModel(): AdminUser
    {
        return new AdminUser();
    }

    public function save(AdminUser $model): AdminUser
    {
        if ($model->isNew()) {
            $model->setId($this->uuid->generate());
            $model->setCreatedAt(new DateTimeImmutable());
        }
        $model->setUpdatedAt(new DateTimeImmutable());
        $model->save();
        $model->refresh();

        return $model;
    }

    public function getOneById(Uuid $id): ?AdminUser
    {
        return $this->model->query()->where(['id' => $id->value()])->limit(1)->one();
    }
}
```

- `save()` owns persistence concerns — it assigns the UUID and `created_at` on insert and bumps `updated_at` on every write. Services never do this.
- `getEmptyModel()` is how a service obtains a new entity; services never `new` a model.
- Parameters are value objects; unwrap with `->value()` only at the query boundary.
- Annotate collection returns for PHPStan: `/** @return AdminUser[] */`.

**Finder naming is a contract:**

| Prefix | Returns | Missing row |
| --- | --- | --- |
| `getOneBy…` | `?Model` | `null` |
| `getList…` | `Model[]` | empty array |
| `has…` | `bool` | `false` |
| `count…` | `int` | `0` |

A repository never throws because a row is absent, and never decides anything — no business rules, no validation, no logging, no object storage.

## 3. App — Services

`Common\App\Service\<Domain>\Service`, `final readonly`, extending `AbstractService`. **One service per domain with many methods** — not one class per use case.

```php
final readonly class Service extends AbstractService
{
    public function __construct(
        LoggerInterface $logger,
        private AdminUserRepository $adminUserRepo,
    ) {
        parent::__construct($logger);
    }
}
```

`LoggerInterface` is not promoted — it passes up to `parent::__construct()`. Everything else is promoted `private`.

Business rules that need the database live here and throw `ValidationException` with a translatable key and the field it belongs to:

```php
if ($this->adminUserRepo->getOneByEmail($dto->email) !== null) {
    throw new ValidationException(messageKey: 'admin_user.email_already_exists', field: 'email');
}
```

## 4. Typed Parameters — Value Objects Instead of Validation

**Every parameter a service takes is typed.** There is no validation step inside a service for the shape of its input, because a badly-shaped value cannot be constructed in the first place.

- **Multi-field input** -> a **Data DTO**: `Common\App\Service\<Domain>\Data\<X>Dto`, `final readonly`, public properties typed as value objects and enums — never scalars.
- **Single value** -> the value object directly: `ban(Uuid $id)`, `changeRole(Uuid $id, AdminUserRole $role)`.

```php
final readonly class CreateDto
{
    public function __construct(
        public Email $email,
        public Text $firstName,
        public NullableText $lastName,
        public NewPassword $password,
        public AdminUserRole $role,
    ) {}
}
```

Everything under `Data\` is a DTO or another **immutable data object** — composite read results are shaped the same way (`FolderContentDto`, `FolderTreeNodeDto`, `FolderWithCountersDto`).

Because the types are value objects, anything reaching a service is already valid. The service only checks what needs the database.

## 5. Common Never Knows HTTP

`Common\` must work unchanged behind any API and behind the console, so it never deals in HTTP.

- A missing entity is **`null`** (or `false` for boolean-shaped operations) — never a `NotFoundException`. Turning that into a 404 is the HTTP layer's job.
- No status codes, no requests, no responses, no cookies in `App\`.

```php
public function ban(Uuid $id): ?AdminUser
{
    if (!$user = $this->adminUserRepo->getOneById($id)) {
        return null;          // the caller decides what absence means
    }
    // …
}
```

This is also what lets a console command reuse the exact same service method.

## 6. Transactions

**The service owns the transaction**, because only the service knows the unit of work — it is the layer calling several repositories. A repository sees one entity and cannot know where the boundary is.

Use the `transaction()` helper on `AbstractService`:

```php
return $this->transaction(function () use ($dto) {
    $order = $this->orderRepo->save($order);
    $this->itemRepo->save($item);

    return $order;
});
```

It commits when the callable returns, rolls back and rethrows when it throws. It works because repositories query through the ambient connection — the same one the transaction opens.

Rules:

- **Only when one unit of work writes through more than one repository.** A lone `$repo->save()` is a single statement; wrapping it adds nothing.
- **Prefer keeping irreversible external effects out of the transaction.** A rollback can undo database writes; it cannot un-delete an uploaded object or un-send an email. Anything that cannot be rolled back is safer after the commit.

## 7. Object Storage and the Database Together

Object storage cannot join a database transaction, so ordering is the tool.

**The database is the source of truth. An orphaned object is acceptable; a row pointing at a missing object is not.**

| | Order | If the second step fails | Result |
| --- | --- | --- | --- |
| **Create** | storage -> database | delete the object (compensate) | orphan object |
| **Delete** | database -> storage | leave the object | orphan object |

Both failures degrade to the same harmless thing — a file nobody references. Orphans can be swept later; a broken reference breaks every download.

Create, with compensation and stream cleanup:

```php
$stored = false;
try {
    $this->objectStorage->put($storageKey, $resource);
    $stored = true;

    return $this->fileRepo->save($file);
} catch (Throwable $exception) {
    if ($stored) {
        try { $this->objectStorage->delete($storageKey); } catch (Throwable) {}
    }
    throw $exception;
} finally {
    $dto->file->close();
}
```

Storage keys are **generated, never derived from user input**, and the original filename is kept as a column:

```php
sprintf('%s/%s/%s.%s', self::STORAGE_PREFIX, date('Y/m'), bin2hex(random_bytes(16)), $extension)
```

## 8. Infra

`Common\Infra\*` wraps every external dependency behind an interface or a factory. Nothing outside `Infra\` should know that the database is PostgreSQL, the cache is Redis, or that files sit on local disk today.

- **`Db\`** — the connection factory and `Db\Migration\` for schema. Every schema change is a migration created with `make yii migrate:create`; never edit the database by hand. Tables `{{%plural_snake_case}}`, UUID primary keys, a real `down()`.
- **`Cache\`** — the cache factory, exposed as both the Yii cache and PSR-16. **Use it only when explicitly needed**, never as a default for reads.
- **`ObjectStorage\`** — `ObjectStorageInterface` (`put` / `get` / `exists` / `delete`, resource streams) with swappable implementations chosen by configuration. **All file I/O goes through this interface** — never touch the filesystem directly, and never load a file fully into memory.
- **`Config\`** — typed access to env vars (`string` / `mustString` / `bool` / `mustBool` / `isDev` / `isProd`). `must*` throws when unset, so required secrets fail at startup rather than at first use. Inject `Config`; never call `getenv()` elsewhere.

Everything here is registered in `common/config/di.php`.

## 9. Shared

`Common\Shared\*` holds what several domains need and **nothing with business logic in it**. If a class knows about admin users, media files, or any other domain concept, it belongs in `App\`.

- **`ValueObject\`** — `Email`, `Uuid`, `Text`, `NullableText`, `NewPassword`
- **`Exception\`** — `ValidationException`, `TranslatableException`, `MustException`, `UnsupportedException`
- **`Util\`** — UUID generation
- **`Translator`** — message-key translation
- **`Http\`** — helpers every API needs: `ResponseFactory`, `PaginationRequest` / `PaginationResponse`, `Rule\`, `Upload\`

`Shared\Http\` is HTTP-shaped but still belongs in Common under the rule in SKILL.md section 2: a second API would need all of it. The `Rule\` attributes are for the HTTP edge only — nothing in `App\` uses them. See `references/http.md`.

## 10. Value Objects

`final readonly`, self-validating in the constructor, exposing `value()`. They are the project's validation strategy, not a type wrapper.

```php
final readonly class Email
{
    private string $value;

    public function __construct(string $value, ?string $field = null)
    {
        $value = trim(mb_strtolower($value));

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('validation.email.invalid', field: $field);
        }

        $this->value = $value;
    }

    public function value(): string { return $this->value; }
}
```

| VO | Guarantees | Normalizes |
| --- | --- | --- |
| `Email` | a valid address | trim + lowercase |
| `Uuid` | a valid UUID (also `equals()`) | trim + lowercase |
| `Text` | non-empty after trim | trim |
| `NullableText` | nothing — `''` becomes `null` | trim |
| `NewPassword` | a minimum length | — |

- The signature is `__construct(string $value, ?string $field = null)`. Pass `field:` at the edge so the error names the input; omit it inside models.
- A new value object means a new `messageKey` in **both** locales of `common/config/i18n.php`. An unknown key is returned verbatim, so a key added to only one locale silently ships the raw string.

## 11. Enums

**Every enum lives in an `Enum\` namespace**, and a fixed set of values is always an enum — never a bare string or int.

```php
namespace Common\App\Models\Enum;

enum AdminUserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
}
```

Case names `PascalCase`, values lowercase strings. Models store the scalar and expose the enum:

```php
public function getRole(): AdminUserRole { return AdminUserRole::from($this->role); }
public function setRole(AdminUserRole $role): void { $this->role = $role->value; }
```

The getter uses `from()`, not `tryFrom()` — an unrecognised stored value should fail loudly.

## 12. Known Deviations

Places where the current code does not match the rules above. Treat the rule as correct.

- **Enums outside `Enum\`** — `Infra\Config\EnvironmentEnum` and `Shared\Http\Upload\UploadedFileType` sit beside their classes instead of in an `Enum\` namespace (section 11).
- **`handleExceptionForApi()` is unused**, and it throws `Shared\Http\Exception\InternalException` from inside `App\` — an HTTP concept below the app layer (section 5).
- **`AdminMedia\Service::deleteFile()` wraps an object storage delete inside `transaction()`.** The order is right (database first), but the storage delete is irreversible and runs before the commit, so a failed commit rolls the row back while the file stays deleted. It also holds a database lock across a storage call (section 6).
