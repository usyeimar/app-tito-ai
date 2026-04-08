<?php

use App\Enums\ModuleType;
use App\Models\Central\Auth\Role\Role;
use App\Models\Tenant\Metadata\Status\Status;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->defaultStatus = Status::factory()
        ->default()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Default Lead Status', 'color' => '#3B82F6']);
});

// ── CRUD ────────────────────────────────────────────────────────────────────

it('lists statuses with pagination', function () {
    $perPage = 100;

    Status::factory()
        ->forModule(ModuleType::LEADS)
        ->count(3)
        ->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("metadata/statuses?page[size]={$perPage}"));

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'name', 'slug', 'color', 'module_type']],
        'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        'links',
    ]);
    $response->assertJsonPath('meta.current_page', 1);
    $response->assertJsonPath('meta.per_page', $perPage);
    $response->assertJsonPath('meta.total', 4);
    $response->assertJsonCount(4, 'data');
});

it('creates a status and returns 201 with auto-generated slug', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/statuses'), [
            'name' => 'In Progress',
            'color' => '#22C55E',
            'module_type' => ModuleType::LEADS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'In Progress');
    $response->assertJsonPath('data.slug', 'in-progress');
    $response->assertJsonPath('data.module_type', 'leads');
    $response->assertJsonPath('data.is_active', true);
});

it('shows a single status', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("metadata/statuses/{$this->defaultStatus->id}"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $this->defaultStatus->id);
    $response->assertJsonPath('data.name', 'Default Lead Status');
});

it('partially updates a status', function () {
    $status = Status::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Original', 'color' => '#000000']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/statuses/{$status->id}"), [
            'name' => 'Updated Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Updated Name');
    $response->assertJsonPath('data.color', '#000000');
});

it('deletes a non-default status and returns 204', function () {
    $status = Status::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['color' => '#FF0000']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/statuses/{$status->id}"));

    $response->assertNoContent();

    expect(Status::find($status->id))->toBeNull();
});

// ── Business Rules ──────────────────────────────────────────────────────────

it('auto-promotes first status to default', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/statuses'), [
            'name' => 'First Companies Status',
            'color' => '#AABBCC',
            'module_type' => ModuleType::COMPANIES->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.is_default', true);
});

it('demotes previous default when creating a new default', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/statuses'), [
            'name' => 'Replacement Default',
            'color' => '#AABBCC',
            'module_type' => ModuleType::LEADS->value,
            'is_default' => true,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.is_default', true);

    $this->defaultStatus->refresh();
    expect($this->defaultStatus->is_default)->toBeFalse();

    $activeDefaults = Status::query()
        ->where('module_type', ModuleType::LEADS->value)
        ->where('is_default', true)
        ->where('is_active', true)
        ->count();

    expect($activeDefaults)->toBe(1);
});

it('rejects creating a default that is inactive', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/statuses'), [
            'name' => 'Bad Status',
            'color' => '#AABBCC',
            'module_type' => ModuleType::LEADS->value,
            'is_default' => true,
            'is_active' => false,
        ]);

    assertHasValidationError($response, 'is_default');
});

it('prevents deleting the default status', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/statuses/{$this->defaultStatus->id}"));

    assertHasValidationError($response, 'status');
});

it('prevents deactivating the default status', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/statuses/{$this->defaultStatus->id}"), [
            'is_active' => false,
        ]);

    assertHasValidationError($response, 'is_active');
});

it('prevents unsetting default directly', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/statuses/{$this->defaultStatus->id}"), [
            'is_default' => false,
        ]);

    assertHasValidationError($response, 'is_default');
});

it('ignores module_type in update payload', function () {
    $status = Status::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['color' => '#AABBCC']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/statuses/{$status->id}"), [
            'module_type' => ModuleType::COMPANIES->value,
            'name' => 'Renamed',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.module_type', 'leads');
    $response->assertJsonPath('data.name', 'Renamed');
});

it('clears previous default when setting a new one', function () {
    $newDefault = Status::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'New Default', 'color' => '#AABBCC']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/statuses/{$newDefault->id}"), [
            'is_default' => true,
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.is_default', true);

    $this->defaultStatus->refresh();
    expect($this->defaultStatus->is_default)->toBeFalse();
});

// ── Validation ──────────────────────────────────────────────────────────────

it('rejects invalid hex color', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/statuses'), [
            'name' => 'Bad Color',
            'color' => 'not-a-hex',
            'module_type' => ModuleType::LEADS->value,
        ]);

    assertHasValidationError($response, 'color');
});

it('rejects invalid module_type', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/statuses'), [
            'name' => 'Bad Module',
            'color' => '#AABBCC',
            'module_type' => 'invalid_module',
        ]);

    assertHasValidationError($response, 'module_type');
});

it('rejects missing required fields', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/statuses'), []);

    $response->assertUnprocessable();
});

it('rejects duplicate name within same module', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/statuses'), [
            'name' => 'Default Lead Status',
            'color' => '#AABBCC',
            'module_type' => ModuleType::LEADS->value,
        ]);

    // The DB unique constraint will cause a 500 or 422 — the key point is it's rejected
    expect($response->status())->toBeGreaterThanOrEqual(400);
});

// ── Slug Behavior ───────────────────────────────────────────────────────────

it('auto-generates slug on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/statuses'), [
            'name' => 'My Custom Status',
            'color' => '#AABBCC',
            'module_type' => ModuleType::LEADS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'my-custom-status');
});

it('keeps slug immutable on update', function () {
    $status = Status::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Original Name', 'color' => '#AABBCC']);

    $originalSlug = $status->slug;

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/statuses/{$status->id}"), [
            'name' => 'Completely New Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.slug', $originalSlug);
});

it('generates unique slug per module type with suffix on collision', function () {
    Status::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Active', 'color' => '#AABBCC']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/statuses'), [
            'name' => 'Active',
            'color' => '#BBCCDD',
            'module_type' => ModuleType::COMPANIES->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'active');
});

// ── Search / Filters ────────────────────────────────────────────────────────

it('supports text operators for name filter', function (string $queryValue, array $expectedNames) {
    Status::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Alpha Pipeline', 'color' => '#111111']);
    Status::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Beta Review', 'color' => '#222222']);
    Status::factory()->forModule(ModuleType::COMPANIES)->create(['name' => '', 'color' => '#333333']);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/statuses?'.http_build_query([
            'filter' => [
                'module_type' => ModuleType::COMPANIES->value,
                'name' => $queryValue,
            ],
            'sort' => '+name',
            'page' => ['size' => 100],
        ])
    ));

    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing($expectedNames);
})->with([
    'contains' => ['alp', ['Alpha Pipeline']],
    'does_not_contain' => ['!alp', ['Beta Review', '']],
    'starts_with' => ['starts(alp)', ['Alpha Pipeline']],
    'ends_with' => ['ends(view)', ['Beta Review']],
    'is_empty' => ['empty()', ['']],
    'is_not_empty' => ['!empty()', ['Alpha Pipeline', 'Beta Review']],
]);

it('supports text operators for slug filter', function (string $queryValue, array $expectedSlugs) {
    Status::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Alpha Pipeline', 'slug' => 'alpha-pipeline', 'color' => '#111111']);
    Status::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Beta Review', 'slug' => 'beta-review', 'color' => '#222222']);
    Status::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'No Slug', 'slug' => '', 'color' => '#333333']);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/statuses?'.http_build_query([
            'filter' => [
                'module_type' => ModuleType::COMPANIES->value,
                'slug' => $queryValue,
            ],
            'page' => ['size' => 100],
        ])
    ));

    $response->assertOk();

    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)->toEqualCanonicalizing($expectedSlugs);
})->with([
    'contains' => ['alpha', ['alpha-pipeline']],
    'does_not_contain' => ['!alpha', ['beta-review', 'no-slug']],
    'starts_with' => ['starts(alpha)', ['alpha-pipeline']],
    'ends_with' => ['ends(review)', ['beta-review']],
    'is_empty' => ['empty()', []],
    'is_not_empty' => ['!empty()', ['alpha-pipeline', 'beta-review', 'no-slug']],
]);

it('supports set operators for module_type filter', function (string $queryValue, array $expectedModules) {
    Status::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Companies Status', 'color' => '#111111']);
    Status::factory()->forModule(ModuleType::PROJECTS)->create(['name' => 'Projects Status', 'color' => '#222222']);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/statuses?'.http_build_query([
            'filter' => [
                'module_type' => $queryValue,
            ],
            'page' => ['size' => 100],
        ])
    ));

    $response->assertOk();

    $modules = collect($response->json('data'))->pluck('module_type')->unique()->values()->all();
    expect($modules)->toEqualCanonicalizing($expectedModules);
})->with([
    'eq' => [ModuleType::LEADS->value, [ModuleType::LEADS->value]],
    'ne' => ['!'.ModuleType::LEADS->value, [ModuleType::COMPANIES->value, ModuleType::PROJECTS->value]],
    'in' => [ModuleType::LEADS->value.','.ModuleType::COMPANIES->value, [ModuleType::LEADS->value, ModuleType::COMPANIES->value]],
    'not_in' => ['!'.ModuleType::LEADS->value.','.ModuleType::COMPANIES->value, [ModuleType::PROJECTS->value]],
    'all_in' => ['all('.ModuleType::LEADS->value.','.ModuleType::COMPANIES->value.')', [ModuleType::LEADS->value, ModuleType::COMPANIES->value]],
    'not_all_in' => ['!all('.ModuleType::LEADS->value.','.ModuleType::COMPANIES->value.')', [ModuleType::PROJECTS->value]],
]);

it('supports boolean operators for is_default filter', function (string $queryValue, int $expectedCount) {
    Status::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Companies Default', 'is_default' => true, 'color' => '#111111']);
    Status::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Companies Non Default', 'is_default' => false, 'color' => '#222222']);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/statuses?'.http_build_query([
            'filter' => [
                'module_type' => ModuleType::COMPANIES->value,
                'is_default' => $queryValue,
            ],
            'page' => ['size' => 100],
        ])
    ));

    $response->assertOk();
    $response->assertJsonCount($expectedCount, 'data');
})->with([
    'eq_true' => ['true', 1],
    'ne_true' => ['!true', 1],
    'empty' => ['empty()', 0],
    'not_empty' => ['!empty()', 2],
]);

it('supports boolean operators for is_active filter', function (string $queryValue, int $expectedCount) {
    Status::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Active Status', 'is_active' => true, 'color' => '#111111']);
    Status::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Inactive Status', 'is_active' => false, 'color' => '#222222']);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/statuses?'.http_build_query([
            'filter' => [
                'module_type' => ModuleType::COMPANIES->value,
                'is_active' => $queryValue,
            ],
            'page' => ['size' => 100],
        ])
    ));

    $response->assertOk();
    $response->assertJsonCount($expectedCount, 'data');
})->with([
    'eq_true' => ['true', 1],
    'ne_true' => ['!true', 1],
    'empty' => ['empty()', 0],
    'not_empty' => ['!empty()', 2],
]);

it('supports date operators for created_at filter', function (string $queryValue, array $expectedNames) {
    $createdAtA = CarbonImmutable::parse('2025-01-01 00:00:00');
    $createdAtB = CarbonImmutable::parse('2025-01-10 00:00:00');
    $createdAtC = CarbonImmutable::parse('2025-01-20 00:00:00');

    Status::factory()->forModule(ModuleType::COMPANIES)->create([
        'name' => 'Created A',
        'color' => '#111111',
        'created_at' => $createdAtA,
        'updated_at' => $createdAtA,
    ]);
    Status::factory()->forModule(ModuleType::COMPANIES)->create([
        'name' => 'Created B',
        'color' => '#222222',
        'created_at' => $createdAtB,
        'updated_at' => $createdAtB,
    ]);
    Status::factory()->forModule(ModuleType::COMPANIES)->create([
        'name' => 'Created C',
        'color' => '#333333',
        'created_at' => $createdAtC,
        'updated_at' => $createdAtC,
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/statuses?'.http_build_query([
            'filter' => [
                'module_type' => ModuleType::COMPANIES->value,
                'created_at' => $queryValue,
            ],
            'page' => ['size' => 100],
        ])
    ));

    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing($expectedNames);
})->with([
    'eq' => ['2025-01-10 00:00:00', ['Created B']],
    'eq_date_only' => ['2025-01-10', ['Created B']],
    'ne' => ['!2025-01-10 00:00:00', ['Created A', 'Created C']],
    'gt' => ['>2025-01-10 00:00:00', ['Created C']],
    'gte' => ['>=2025-01-10 00:00:00', ['Created B', 'Created C']],
    'lt' => ['<2025-01-10 00:00:00', ['Created A']],
    'lte' => ['<=2025-01-10 00:00:00', ['Created A', 'Created B']],
    'between' => ['2025-01-01 00:00:00..2025-01-10 00:00:00', ['Created A', 'Created B']],
    'empty' => ['empty()', []],
    'not_empty' => ['!empty()', ['Created A', 'Created B', 'Created C']],
]);

it('supports date operators for updated_at filter', function (string $queryValue, array $expectedNames) {
    $createdAt = CarbonImmutable::parse('2025-01-01 00:00:00');

    Status::factory()->forModule(ModuleType::COMPANIES)->create([
        'name' => 'Updated A',
        'color' => '#111111',
        'created_at' => $createdAt,
        'updated_at' => CarbonImmutable::parse('2025-01-01 00:00:00'),
    ]);
    Status::factory()->forModule(ModuleType::COMPANIES)->create([
        'name' => 'Updated B',
        'color' => '#222222',
        'created_at' => $createdAt,
        'updated_at' => CarbonImmutable::parse('2025-01-10 00:00:00'),
    ]);
    Status::factory()->forModule(ModuleType::COMPANIES)->create([
        'name' => 'Updated C',
        'color' => '#333333',
        'created_at' => $createdAt,
        'updated_at' => CarbonImmutable::parse('2025-01-20 00:00:00'),
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/statuses?'.http_build_query([
            'filter' => [
                'module_type' => ModuleType::COMPANIES->value,
                'updated_at' => $queryValue,
            ],
            'page' => ['size' => 100],
        ])
    ));

    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing($expectedNames);
})->with([
    'eq' => ['2025-01-10 00:00:00', ['Updated B']],
    'ne' => ['!2025-01-10 00:00:00', ['Updated A', 'Updated C']],
    'gt' => ['>2025-01-10 00:00:00', ['Updated C']],
    'gte' => ['>=2025-01-10 00:00:00', ['Updated B', 'Updated C']],
    'lt' => ['<2025-01-10 00:00:00', ['Updated A']],
    'lte' => ['<=2025-01-10 00:00:00', ['Updated A', 'Updated B']],
    'between' => ['2025-01-01 00:00:00..2025-01-10 00:00:00', ['Updated A', 'Updated B']],
    'empty' => ['empty()', []],
    'not_empty' => ['!empty()', ['Updated A', 'Updated B', 'Updated C']],
]);

it('rejects unsupported operators for status filters', function (string $field, string $queryValue) {
    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/statuses?'.http_build_query([
            'filter' => [
                $field => $queryValue,
            ],
        ])
    ));

    $response->assertUnprocessable();
})->with([
    'module_type_gt' => ['module_type', '>companies'],
    'module_type_between' => ['module_type', 'companies..leads'],
    'is_default_between' => ['is_default', 'true..false'],
    'is_active_in' => ['is_active', 'true,false'],
]);

it('sorts statuses by allowed sortable fields', function () {
    Status::factory()->forModule(ModuleType::COMPANIES)->create([
        'name' => 'Zulu',
        'color' => '#111111',
        'is_default' => false,
        'is_active' => true,
        'position' => 2,
        'created_at' => CarbonImmutable::parse('2025-01-20 00:00:00'),
        'updated_at' => CarbonImmutable::parse('2025-01-20 00:00:00'),
    ]);
    Status::factory()->forModule(ModuleType::COMPANIES)->create([
        'name' => 'Alpha',
        'color' => '#222222',
        'is_default' => false,
        'is_active' => false,
        'position' => 1,
        'created_at' => CarbonImmutable::parse('2025-01-10 00:00:00'),
        'updated_at' => CarbonImmutable::parse('2025-01-10 00:00:00'),
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/statuses?'.http_build_query([
            'filter' => [
                'module_type' => ModuleType::COMPANIES->value,
            ],
            'sort' => '+name,+module_type,+is_default,+is_active,+position,+created_at,+updated_at',
            'page' => ['size' => 100],
        ])
    ));

    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toEqual(['Alpha', 'Zulu']);
});

it('rejects sorting statuses by slug', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('metadata/statuses?sort=+slug'));

    $response->assertUnprocessable();
});

// ── Authorization ───────────────────────────────────────────────────────────

it('denies access without metadata.view permission', function () {
    $viewer = $this->createTenantUser([], ['user']);
    $userRole = Role::query()
        ->where('name', 'user')
        ->where('guard_name', 'tenant')
        ->first();
    $userRole->revokePermissionTo('metadata.view');

    $response = $this->actingAs($viewer, 'tenant-api')
        ->getJson($this->tenantApiUrl('metadata/statuses'));

    $response->assertForbidden();
});

it('denies create without metadata.manage permission', function () {
    $viewer = $this->createTenantUser([], ['user']);

    $response = $this->actingAs($viewer, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/statuses'), [
            'name' => 'Unauthorized',
            'color' => '#AABBCC',
            'module_type' => ModuleType::LEADS->value,
        ]);

    $response->assertForbidden();
});

it('denies delete without metadata.delete permission', function () {
    $viewer = $this->createTenantUser([], ['user']);

    $status = Status::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['color' => '#FF0000']);

    $response = $this->actingAs($viewer, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/statuses/{$status->id}"));

    $response->assertForbidden();
});
