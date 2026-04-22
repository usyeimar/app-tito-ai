# Tito AI — Architecture & Code Conventions

This document defines the canonical patterns for this codebase. Follow these conventions when creating or modifying code. When existing code deviates from these patterns, prefer these guidelines for new code.

## Architecture Overview

Multi-tenant SaaS with two contexts:
- **Central** (`App\*\Central\*`) — User accounts, tenancy management, auth
- **Tenant** (`App\*\Tenant\*`) — Workspace-scoped features (CRM, Agents, KnowledgeBase)

## Backend Layers (Request → Response)

```
Route → FormRequest → Controller → Action/Service → Model → Data/Resource → Response
```

### When to Use Actions vs Services

- **Actions** — Single-operation classes for domain CRUD. One action = one use case. Use `__invoke()`. Preferred for new features.
- **Services** — Multi-method classes for complex domains with shared state or cross-cutting logic (auth, invitations, user management). Use when operations share authorization helpers or transaction boundaries.

## Action Classes

Location: `app/Actions/Tenant/{Domain}/`

```php
<?php

declare(strict_types=1);

namespace App\Actions\Tenant\{Domain};

final class Create{Entity}
{
    public function __invoke(Create{Entity}Data $data): {Entity}Data
    {
        return DB::transaction(function () use ($data): {Entity}Data {
            $model = {Entity}::create([
                'name' => $data->name,
                // ... map fields explicitly
            ]);

            return {Entity}Data::from($model);
        });
    }
}
```

Rules:
- Always `final` and `declare(strict_types=1)`
- Invokable (`__invoke`) — one public method per action
- Accept typed `Data` objects, return typed `Data` objects or `void`
- Wrap multi-model writes in `DB::transaction()`
- Use `array_filter(fn ($v) => $v !== null)` for partial updates
- Private helpers for side effects (Redis sync, event dispatch)

## Data Objects (Spatie Laravel Data)

Location: `app/Data/Tenant/{Domain}/`

Three types per entity:

```php
// Create DTO — required fields, defaults for optional
class Create{Entity}Data extends Data
{
    public function __construct(
        public string $name,
        public ?string $description = null,
    ) {}
}

// Update DTO — all nullable for partial updates
class Update{Entity}Data extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
    ) {}
}

// Read DTO — full representation for responses
class {Entity}Data extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function from{Entity}({Entity} $model): self
    {
        return new self(
            id: (string) $model->id,
            name: $model->name,
            description: $model->description,
            created_at: $model->created_at?->toISOString() ?? '',
            updated_at: $model->updated_at?->toISOString() ?? '',
        );
    }
}
```

## FormRequests

Location: `app/Http/Requests/Tenant/API/{Domain}/` or `app/Http/Requests/Tenant/{Domain}/`

Naming convention matches controller action:
- `Store{Entity}Request` — for `store()` (creation)
- `Update{Entity}Request` — for `update()`
- `Index{Entity}Request` — for `index()` (list/search/filter)

```php
class Store{Entity}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handles authorization
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
```

Rules:
- Store: `required` for mandatory, `nullable` for optional
- Update: `sometimes` instead of `required` for partial updates
- Index: use `HasCanonicalSearchRules` trait for search/filter/pagination
- Authorization delegated to policies — `authorize()` returns `true`

## Controllers

Location: `app/Http/Controllers/Tenant/API/{Domain}/`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\{Domain};

class {Entity}Controller extends Controller
{
    public function index(Index{Entity}Request $request, List{Entities} $action): JsonResponse
    {
        Gate::authorize('viewAny', {Entity}::class);

        $items = $action($request->validated());

        return response()->json(['data' => $items]);
    }

    public function store(Store{Entity}Request $request, Create{Entity} $action): JsonResponse
    {
        Gate::authorize('create', {Entity}::class);

        $data = Create{Entity}Data::from($request->validated());
        $result = $action($data);

        return response()->json(['data' => $result, 'message' => '{Entity} created.'], 201);
    }

    public function show({Entity} $entity, Show{Entity} $action): JsonResponse
    {
        Gate::authorize('view', $entity);

        return response()->json(['data' => $action($entity)]);
    }

    public function update(Update{Entity}Request $request, {Entity} $entity, Update{Entity} $action): JsonResponse
    {
        Gate::authorize('update', $entity);

        $data = Update{Entity}Data::from($request->validated());
        $result = $action($entity, $data);

        return response()->json(['data' => $result, 'message' => '{Entity} updated.']);
    }

    public function destroy({Entity} $entity, Delete{Entity} $action): Response
    {
        Gate::authorize('delete', $entity);

        $action($entity);

        return response()->noContent();
    }
}
```

Rules:
- No constructor — inject Actions per method
- `Gate::authorize()` at the top of each method
- `declare(strict_types=1)` always
- `destroy` returns `response()->noContent()` (204)
- Response envelope: `{ "data": ..., "message": "..." }`

## Policies

Location: `app/Policies/` or `app/Policies/Tenant/{Domain}/`

```php
class {Entity}Policy extends ModulePolicy
{
    protected string $module = '{entity}'; // matches TenantPermissionRegistry key
}
```

- Extend `ModulePolicy` for permission-gated modules — inherits `viewAny`, `view`, `create`, `update`, `delete`
- `ModulePolicy` checks `{module}.view`, `{module}.manage`, `{module}.delete` permissions
- Write operations require verified email (`isVerified()`)
- Standalone policies only for simple cases (e.g., AgentPolicy just checks email verification)

## Models

Location: `app/Models/Tenant/{Domain}/`

```php
class {Entity} extends Model
{
    use HasFactory, HasUlids;

    protected $table = '{entities}';

    protected $fillable = ['name', 'slug', 'description'];

    protected $casts = [
        'config' => 'array',
    ];
}
```

- Always `HasUlids` for primary keys
- `HasFactory` with explicit `@use HasFactory<{Entity}Factory>` PHPDoc
- `HasSlug` (Spatie) when entity needs URL-friendly slugs
- Explicit `$table` property
- `$fillable` array (not `$guarded`)

## Factories

Location: `database/factories/Tenant/{Domain}/`

```php
class {Entity}Factory extends Factory
{
    protected $model = {Entity}::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
        ];
    }

    // Named states for variations
    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'active']);
    }
}
```

- Use `configure()` with `afterCreating()` for automatic relationship setup
- State methods return `static` for chaining
- Use `fake()` helper (not `$this->faker`)

## Routes

Location: `routes/tenant/api/{domain}/`

```php
// routes/tenant/api/{domain}/{entities}.php
Route::apiResource('{entities}', {Entity}Controller::class);
```

- Hub-and-spoke: `routes/tenant/api.php` requires domain sub-files
- URL segments in kebab-case: `knowledge-bases`, `test-call`
- `apiResource` for standard CRUD
- Named routes for non-CRUD endpoints only

## Tests (Pest)

Location: `tests/Feature/Tenant/API/{Domain}/{Controller}Test.php`

```php
<?php

use App\Models\Tenant\{Domain}\{Entity};

describe('{Entity} API', function () {
    describe('Authentication', function () {
        it('requires authentication', function () {
            $this->getJson($this->tenantApiUrl('{entities}'))
                ->assertUnauthorized();
        });
    });

    describe('Authorization', function () {
        it('forbids users without permissions', function () {
            $user = User::factory()->create();

            $this->actingAs($user, 'tenant-api')
                ->getJson($this->tenantApiUrl('{entities}'))
                ->assertForbidden();
        });
    });

    describe('List', function () {
        it('returns paginated {entities}', function () {
            {Entity}::factory()->count(3)->create();

            $this->actingAs($this->user, 'tenant-api')
                ->getJson($this->tenantApiUrl('{entities}'))
                ->assertOk()
                ->assertJsonCount(3, 'data');
        });
    });

    describe('Create', function () {
        it('creates a new {entity}', function () {
            $this->actingAs($this->user, 'tenant-api')
                ->postJson($this->tenantApiUrl('{entities}'), [
                    'name' => 'Test {Entity}',
                ])
                ->assertCreated()
                ->assertJsonPath('data.name', 'Test {Entity}');
        });
    });

    describe('Update', function () {
        it('updates an existing {entity}', function () {
            $entity = {Entity}::factory()->create();

            $this->actingAs($this->user, 'tenant-api')
                ->putJson($this->tenantApiUrl("{entities}/{$entity->id}"), [
                    'name' => 'Updated',
                ])
                ->assertOk()
                ->assertJsonPath('data.name', 'Updated');
        });
    });

    describe('Delete', function () {
        it('deletes an {entity}', function () {
            $entity = {Entity}::factory()->create();

            $this->actingAs($this->user, 'tenant-api')
                ->deleteJson($this->tenantApiUrl("{entities}/{$entity->id}"))
                ->assertNoContent();

            expect({Entity}::find($entity->id))->toBeNull();
        });
    });
});
```

Rules:
- Files under `tests/Feature/Tenant/` auto-extend `TenantTestCase` (configured in `Pest.php`)
- `$this->user` is a pre-created super_admin from `TenantTestCase`
- `$this->tenantApiUrl('path')` builds `/{tenant-slug}/api/path`
- Nested `describe()` blocks: Authentication → Authorization → CRUD operations
- Auth test first, then authorization, then happy paths
- Use `assertJsonPath()` for specific values, `assertJsonCount()` for lists
- Use `expect($model->fresh()->field)->toBe('value')` for DB verification
- Use factories with states — never manually set fields that a factory state covers

## Permissions

Register new modules in `app/Support/Permissions/TenantPermissionRegistry.php`:

```php
['key' => '{entity}', 'label' => '{Entities}'],
```

Default actions are `view`, `manage`, `delete`. The seeder auto-creates permissions from this registry.

## File Organization Summary

```
app/
├── Actions/Tenant/{Domain}/          # Single-use invokable actions
├── Data/Tenant/{Domain}/             # Spatie Data DTOs (Create, Update, Read)
├── Http/
│   ├── Controllers/Tenant/API/{Domain}/
│   ├── Requests/Tenant/API/{Domain}/ # FormRequests (Store, Update, Index)
│   └── Resources/Tenant/API/{Domain}/ # API Resources (when needed)
├── Models/Tenant/{Domain}/
├── Policies/                         # or Policies/Tenant/{Domain}/
├── Services/Tenant/{Domain}/         # Multi-method service classes
└── Support/Permissions/              # Permission registry
database/
├── factories/Tenant/{Domain}/
├── migrations/tenant/{Domain}/
└── seeders/Tenant/
routes/tenant/api/{domain}/
tests/Feature/Tenant/API/{Domain}/
```
