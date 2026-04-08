<?php

use App\Enums\ModuleType;
use App\Models\Central\Auth\Role\Role;
use App\Models\Tenant\Metadata\Priority\Priority;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->defaultPriority = Priority::factory()
        ->default()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Default Lead Priority', 'color' => '#3B82F6']);
});

// ── CRUD ────────────────────────────────────────────────────────────────────

it('lists priorities with pagination', function () {
    Priority::factory()
        ->forModule(ModuleType::LEADS)
        ->count(2)
        ->create();

    Priority::factory()
        ->forModule(ModuleType::PROJECTS)
        ->create(['color' => '#AABBCC']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('metadata/priorities'));

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            ['id', 'name', 'slug', 'color', 'module_type', 'is_default', 'is_active'],
        ],
        'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        'links',
    ]);

    // The default priority from beforeEach is also in leads (3 leads + 1 project = 4 total)
    expect($response->json('meta.total'))->toBe(4);
    expect(count($response->json('data')))->toBe(4);
});

it('creates a priority and returns 201 with auto-generated slug', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/priorities'), [
            'name' => 'High Priority',
            'color' => '#22C55E',
            'module_type' => ModuleType::LEADS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'High Priority');
    $response->assertJsonPath('data.slug', 'high-priority');
    $response->assertJsonPath('data.module_type', 'leads');
    $response->assertJsonPath('data.is_active', true);
});

it('shows a single priority', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("metadata/priorities/{$this->defaultPriority->id}"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $this->defaultPriority->id);
    $response->assertJsonPath('data.name', 'Default Lead Priority');
});

it('partially updates a priority', function () {
    $priority = Priority::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Original', 'color' => '#000000']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/priorities/{$priority->id}"), [
            'name' => 'Updated Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Updated Name');
    $response->assertJsonPath('data.color', '#000000');
});

it('deletes a non-default priority and returns 204', function () {
    $priority = Priority::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['color' => '#FF0000']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/priorities/{$priority->id}"));

    $response->assertNoContent();

    expect(Priority::find($priority->id))->toBeNull();
});

// ── Business Rules ──────────────────────────────────────────────────────────

it('auto-promotes first priority to default', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/priorities'), [
            'name' => 'First Projects Priority',
            'color' => '#AABBCC',
            'module_type' => ModuleType::PROJECTS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.is_default', true);
});

it('demotes previous default when creating a new default', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/priorities'), [
            'name' => 'Replacement Default',
            'color' => '#AABBCC',
            'module_type' => ModuleType::LEADS->value,
            'is_default' => true,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.is_default', true);

    $this->defaultPriority->refresh();
    expect($this->defaultPriority->is_default)->toBeFalse();

    $activeDefaults = Priority::query()
        ->where('module_type', ModuleType::LEADS->value)
        ->where('is_default', true)
        ->where('is_active', true)
        ->count();

    expect($activeDefaults)->toBe(1);
});

it('rejects creating a default that is inactive', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/priorities'), [
            'name' => 'Bad Priority',
            'color' => '#AABBCC',
            'module_type' => ModuleType::LEADS->value,
            'is_default' => true,
            'is_active' => false,
        ]);

    assertHasValidationError($response, 'is_default');
});

it('prevents deleting the default priority', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/priorities/{$this->defaultPriority->id}"));

    assertHasValidationError($response, 'priority');
});

it('prevents deactivating the default priority', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/priorities/{$this->defaultPriority->id}"), [
            'is_active' => false,
        ]);

    assertHasValidationError($response, 'is_active');
});

it('prevents unsetting default directly', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/priorities/{$this->defaultPriority->id}"), [
            'is_default' => false,
        ]);

    assertHasValidationError($response, 'is_default');
});

it('ignores module_type in update payload', function () {
    $priority = Priority::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['color' => '#AABBCC']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/priorities/{$priority->id}"), [
            'module_type' => ModuleType::PROJECTS->value,
            'name' => 'Renamed',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.module_type', 'leads');
    $response->assertJsonPath('data.name', 'Renamed');
});

it('clears previous default when setting a new one', function () {
    $newDefault = Priority::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'New Default', 'color' => '#AABBCC']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/priorities/{$newDefault->id}"), [
            'is_default' => true,
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.is_default', true);

    $this->defaultPriority->refresh();
    expect($this->defaultPriority->is_default)->toBeFalse();
});

it('keeps exactly one default after sequential default promotions', function () {
    $priorityA = Priority::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['color' => '#AAAAAA']);

    $priorityB = Priority::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['color' => '#BBBBBB']);

    $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/priorities/{$priorityA->id}"), ['is_default' => true])
        ->assertOk();

    $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/priorities/{$priorityB->id}"), ['is_default' => true])
        ->assertOk();

    $defaultCount = Priority::query()
        ->where('module_type', ModuleType::LEADS->value)
        ->where('is_default', true)
        ->count();

    expect($defaultCount)->toBe(1);
    expect($priorityB->refresh()->is_default)->toBeTrue();
});

// ── Validation ──────────────────────────────────────────────────────────────

it('rejects invalid hex color', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/priorities'), [
            'name' => 'Bad Color',
            'color' => 'not-a-hex',
            'module_type' => ModuleType::LEADS->value,
        ]);

    assertHasValidationError($response, 'color');
});

it('rejects invalid module_type', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/priorities'), [
            'name' => 'Bad Module',
            'color' => '#AABBCC',
            'module_type' => 'invalid_module',
        ]);

    assertHasValidationError($response, 'module_type');
});

it('rejects missing required fields', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/priorities'), []);

    $response->assertUnprocessable();
});

it('rejects duplicate name within same module', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/priorities'), [
            'name' => 'Default Lead Priority',
            'color' => '#AABBCC',
            'module_type' => ModuleType::LEADS->value,
        ]);

    $response->assertUnprocessable();
});

// ── Slug Behavior ───────────────────────────────────────────────────────────

it('auto-generates slug on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/priorities'), [
            'name' => 'My Custom Priority',
            'color' => '#AABBCC',
            'module_type' => ModuleType::LEADS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'my-custom-priority');
});

it('keeps slug immutable on update', function () {
    $priority = Priority::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Original Name', 'color' => '#AABBCC']);

    $originalSlug = $priority->slug;

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/priorities/{$priority->id}"), [
            'name' => 'Completely New Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.slug', $originalSlug);
});

it('generates unique slug per module type with suffix on collision', function () {
    Priority::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Critical', 'color' => '#AABBCC']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/priorities'), [
            'name' => 'Critical',
            'color' => '#BBCCDD',
            'module_type' => ModuleType::PROJECTS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'critical');
});

// ── Icon and Position ───────────────────────────────────────────────────────

it('accepts optional icon field', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/priorities'), [
            'name' => 'Urgent',
            'color' => '#FF0000',
            'module_type' => ModuleType::LEADS->value,
            'icon' => 'fire',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.icon', 'fire');
});

it('accepts position field', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/priorities'), [
            'name' => 'High',
            'color' => '#FF5733',
            'module_type' => ModuleType::LEADS->value,
            'position' => 2,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.position', 2);
});

// ── Search / Filters ────────────────────────────────────────────────────────

it('supports text operators for name filter', function (string $queryValue, array $expectedNames) {
    Priority::factory()->forModule(ModuleType::PROJECTS)->create(['name' => 'Alpha Priority', 'color' => '#111111']);
    Priority::factory()->forModule(ModuleType::PROJECTS)->create(['name' => 'Beta Queue', 'color' => '#222222']);
    Priority::factory()->forModule(ModuleType::PROJECTS)->create(['name' => '', 'color' => '#333333']);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/priorities?'.http_build_query([
            'filter' => [
                'module_type' => ModuleType::PROJECTS->value,
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
    'contains' => ['alp', ['Alpha Priority']],
    'does_not_contain' => ['!alp', ['Beta Queue', '']],
    'starts_with' => ['starts(alp)', ['Alpha Priority']],
    'ends_with' => ['ends(queue)', ['Beta Queue']],
    'is_empty' => ['empty()', ['']],
    'is_not_empty' => ['!empty()', ['Alpha Priority', 'Beta Queue']],
]);

it('supports text operators for slug filter', function (string $queryValue, array $expectedSlugs) {
    Priority::factory()->forModule(ModuleType::PROJECTS)->create(['name' => 'Alpha Priority', 'slug' => 'alpha-priority', 'color' => '#111111']);
    Priority::factory()->forModule(ModuleType::PROJECTS)->create(['name' => 'Beta Queue', 'slug' => 'beta-queue', 'color' => '#222222']);
    Priority::factory()->forModule(ModuleType::PROJECTS)->create(['name' => 'No Slug', 'slug' => '', 'color' => '#333333']);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/priorities?'.http_build_query([
            'filter' => [
                'module_type' => ModuleType::PROJECTS->value,
                'slug' => $queryValue,
            ],
            'page' => ['size' => 100],
        ])
    ));

    $response->assertOk();

    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)->toEqualCanonicalizing($expectedSlugs);
})->with([
    'contains' => ['alpha', ['alpha-priority']],
    'does_not_contain' => ['!alpha', ['beta-queue', 'no-slug']],
    'starts_with' => ['starts(alpha)', ['alpha-priority']],
    'ends_with' => ['ends(queue)', ['beta-queue']],
    'is_empty' => ['empty()', []],
    'is_not_empty' => ['!empty()', ['alpha-priority', 'beta-queue', 'no-slug']],
]);

it('supports set operators for module_type filter', function (string $queryValue, array $expectedModules) {
    Priority::factory()->forModule(ModuleType::PROJECTS)->create(['name' => 'Projects Priority', 'color' => '#111111']);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/priorities?'.http_build_query([
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
    'ne' => ['!'.ModuleType::LEADS->value, [ModuleType::PROJECTS->value]],
    'in' => [ModuleType::LEADS->value.','.ModuleType::PROJECTS->value, [ModuleType::LEADS->value, ModuleType::PROJECTS->value]],
    'not_in' => ['!'.ModuleType::LEADS->value, [ModuleType::PROJECTS->value]],
    'all_in' => ['all('.ModuleType::LEADS->value.','.ModuleType::PROJECTS->value.')', [ModuleType::LEADS->value, ModuleType::PROJECTS->value]],
    'not_all_in' => ['!all('.ModuleType::LEADS->value.')', [ModuleType::PROJECTS->value]],
]);

it('supports boolean operators for is_default filter', function (string $queryValue, int $expectedCount) {
    Priority::factory()->forModule(ModuleType::PROJECTS)->create(['name' => 'Projects Default', 'is_default' => true, 'color' => '#111111']);
    Priority::factory()->forModule(ModuleType::PROJECTS)->create(['name' => 'Projects Non Default', 'is_default' => false, 'color' => '#222222']);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/priorities?'.http_build_query([
            'filter' => [
                'module_type' => ModuleType::PROJECTS->value,
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
    Priority::factory()->forModule(ModuleType::PROJECTS)->create(['name' => 'Active Priority', 'is_active' => true, 'color' => '#111111']);
    Priority::factory()->forModule(ModuleType::PROJECTS)->create(['name' => 'Inactive Priority', 'is_active' => false, 'color' => '#222222']);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/priorities?'.http_build_query([
            'filter' => [
                'module_type' => ModuleType::PROJECTS->value,
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

    Priority::factory()->forModule(ModuleType::PROJECTS)->create([
        'name' => 'Created A',
        'color' => '#111111',
        'created_at' => $createdAtA,
        'updated_at' => $createdAtA,
    ]);
    Priority::factory()->forModule(ModuleType::PROJECTS)->create([
        'name' => 'Created B',
        'color' => '#222222',
        'created_at' => $createdAtB,
        'updated_at' => $createdAtB,
    ]);
    Priority::factory()->forModule(ModuleType::PROJECTS)->create([
        'name' => 'Created C',
        'color' => '#333333',
        'created_at' => $createdAtC,
        'updated_at' => $createdAtC,
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/priorities?'.http_build_query([
            'filter' => [
                'module_type' => ModuleType::PROJECTS->value,
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

    Priority::factory()->forModule(ModuleType::PROJECTS)->create([
        'name' => 'Updated A',
        'color' => '#111111',
        'created_at' => $createdAt,
        'updated_at' => CarbonImmutable::parse('2025-01-01 00:00:00'),
    ]);
    Priority::factory()->forModule(ModuleType::PROJECTS)->create([
        'name' => 'Updated B',
        'color' => '#222222',
        'created_at' => $createdAt,
        'updated_at' => CarbonImmutable::parse('2025-01-10 00:00:00'),
    ]);
    Priority::factory()->forModule(ModuleType::PROJECTS)->create([
        'name' => 'Updated C',
        'color' => '#333333',
        'created_at' => $createdAt,
        'updated_at' => CarbonImmutable::parse('2025-01-20 00:00:00'),
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/priorities?'.http_build_query([
            'filter' => [
                'module_type' => ModuleType::PROJECTS->value,
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
    'eq_date_only' => ['2025-01-10', ['Updated B']],
    'ne' => ['!2025-01-10 00:00:00', ['Updated A', 'Updated C']],
    'gt' => ['>2025-01-10 00:00:00', ['Updated C']],
    'gte' => ['>=2025-01-10 00:00:00', ['Updated B', 'Updated C']],
    'lt' => ['<2025-01-10 00:00:00', ['Updated A']],
    'lte' => ['<=2025-01-10 00:00:00', ['Updated A', 'Updated B']],
    'between' => ['2025-01-01 00:00:00..2025-01-10 00:00:00', ['Updated A', 'Updated B']],
    'empty' => ['empty()', []],
    'not_empty' => ['!empty()', ['Updated A', 'Updated B', 'Updated C']],
]);

it('rejects unsupported operators for priority filters', function (string $field, string $queryValue) {
    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/priorities?'.http_build_query([
            'filter' => [
                $field => $queryValue,
            ],
        ])
    ));

    $response->assertUnprocessable();
})->with([
    'module_type_gt' => ['module_type', '>projects'],
    'module_type_between' => ['module_type', 'projects..leads'],
    'is_default_between' => ['is_default', 'true..false'],
    'is_active_in' => ['is_active', 'true,false'],
]);

it('sorts priorities by allowed sortable fields', function () {
    Priority::factory()->forModule(ModuleType::PROJECTS)->create([
        'name' => 'Zulu',
        'color' => '#111111',
        'is_default' => false,
        'is_active' => true,
        'position' => 2,
        'created_at' => CarbonImmutable::parse('2025-01-20 00:00:00'),
        'updated_at' => CarbonImmutable::parse('2025-01-20 00:00:00'),
    ]);
    Priority::factory()->forModule(ModuleType::PROJECTS)->create([
        'name' => 'Alpha',
        'color' => '#222222',
        'is_default' => false,
        'is_active' => false,
        'position' => 1,
        'created_at' => CarbonImmutable::parse('2025-01-10 00:00:00'),
        'updated_at' => CarbonImmutable::parse('2025-01-10 00:00:00'),
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')->getJson($this->tenantApiUrl(
        'metadata/priorities?'.http_build_query([
            'filter' => [
                'module_type' => ModuleType::PROJECTS->value,
            ],
            'sort' => '+name,+module_type,+is_default,+is_active,+position,+created_at,+updated_at',
            'page' => ['size' => 100],
        ])
    ));

    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toEqual(['Alpha', 'Zulu']);
});

it('rejects sorting priorities by slug', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('metadata/priorities?sort=+slug'));

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
        ->getJson($this->tenantApiUrl('metadata/priorities'));

    $response->assertForbidden();
});

it('denies create without metadata.manage permission', function () {
    $viewer = $this->createTenantUser([], ['user']);

    $response = $this->actingAs($viewer, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/priorities'), [
            'name' => 'Unauthorized',
            'color' => '#AABBCC',
            'module_type' => ModuleType::LEADS->value,
        ]);

    $response->assertForbidden();
});

it('denies delete without metadata.delete permission', function () {
    $viewer = $this->createTenantUser([], ['user']);

    $priority = Priority::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['color' => '#FF0000']);

    $response = $this->actingAs($viewer, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/priorities/{$priority->id}"));

    $response->assertForbidden();
});
