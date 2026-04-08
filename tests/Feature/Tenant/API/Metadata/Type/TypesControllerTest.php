<?php

use App\Enums\ModuleType;
use App\Models\Central\Auth\Role\Role;
use App\Models\Tenant\Metadata\Type\Type;

// ── CRUD ────────────────────────────────────────────────────────────────────

it('lists types with pagination', function () {
    Type::factory()
        ->forModule(ModuleType::CONTACTS)
        ->count(3)
        ->create();

    Type::factory()
        ->forModule(ModuleType::COMPANIES)
        ->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('metadata/types'));

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            ['id', 'name', 'slug', 'icon', 'module_type', 'is_active', 'position'],
        ],
        'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        'links',
    ]);

    expect($response->json('meta.total'))->toBe(4);
});

it('creates a type and returns 201 with auto-generated slug', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/types'), [
            'name' => 'Individual Client',
            'module_type' => ModuleType::CONTACTS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Individual Client');
    $response->assertJsonPath('data.slug', 'individual-client');
    $response->assertJsonPath('data.module_type', 'contacts');
    $response->assertJsonPath('data.is_active', true);
});

it('shows a single type', function () {
    $type = Type::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create(['name' => 'Test Type']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("metadata/types/{$type->id}"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $type->id);
    $response->assertJsonPath('data.name', 'Test Type');
});

it('partially updates a type', function () {
    $type = Type::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create(['name' => 'Original']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/types/{$type->id}"), [
            'name' => 'Updated Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Updated Name');
});

it('deletes a type and returns 204', function () {
    $type = Type::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/types/{$type->id}"));

    $response->assertNoContent();
    expect(Type::find($type->id))->toBeNull();
});

// ── Business Rules ──────────────────────────────────────────────────────────

it('ignores module_type in update payload', function () {
    $type = Type::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/types/{$type->id}"), [
            'module_type' => ModuleType::COMPANIES->value,
            'name' => 'Renamed',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.module_type', 'contacts');
    $response->assertJsonPath('data.name', 'Renamed');
});

// ── Validation ──────────────────────────────────────────────────────────────

it('rejects invalid module_type', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/types'), [
            'name' => 'Bad Module',
            'module_type' => 'invalid_module',
        ]);

    assertHasValidationError($response, 'module_type');
});

it('rejects unsupported module_type for types', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/types'), [
            'name' => 'Bad Module',
            'module_type' => ModuleType::VEHICLES->value,
        ]);

    assertHasValidationError($response, 'module_type');
});

it('rejects missing required fields', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/types'), []);

    $response->assertUnprocessable();
});

it('rejects duplicate name within same module', function () {
    Type::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create(['name' => 'Duplicate Name']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/types'), [
            'name' => 'Duplicate Name',
            'module_type' => ModuleType::CONTACTS->value,
        ]);

    $response->assertUnprocessable();
});

it('allows same name in different modules', function () {
    Type::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create(['name' => 'Other']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/types'), [
            'name' => 'Other',
            'module_type' => ModuleType::COMPANIES->value,
        ]);

    $response->assertCreated();
});

// ── Slug Behavior ───────────────────────────────────────────────────────────

it('auto-generates slug on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/types'), [
            'name' => 'My Custom Type',
            'module_type' => ModuleType::CONTACTS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'my-custom-type');
});

it('keeps slug immutable on update', function () {
    $type = Type::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create(['name' => 'Original Name']);

    $originalSlug = $type->slug;

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/types/{$type->id}"), [
            'name' => 'Completely New Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.slug', $originalSlug);
});

it('generates unique slug per module type', function () {
    Type::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create(['name' => 'Active']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/types'), [
            'name' => 'Active',
            'module_type' => ModuleType::COMPANIES->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'active');
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
        ->getJson($this->tenantApiUrl('metadata/types'));

    $response->assertForbidden();
});

it('denies create without metadata.manage permission', function () {
    $viewer = $this->createTenantUser([], ['user']);

    $response = $this->actingAs($viewer, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/types'), [
            'name' => 'Unauthorized',
            'module_type' => ModuleType::CONTACTS->value,
        ]);

    $response->assertForbidden();
});

it('denies delete without metadata.delete permission', function () {
    $viewer = $this->createTenantUser([], ['user']);

    $type = Type::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create();

    $response = $this->actingAs($viewer, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/types/{$type->id}"));

    $response->assertForbidden();
});
