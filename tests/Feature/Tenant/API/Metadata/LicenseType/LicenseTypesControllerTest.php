<?php

use App\Enums\ModuleType;
use App\Models\Central\Auth\Role\Role;
use App\Models\Tenant\Metadata\LicenseType\LicenseType;

// ── CRUD ────────────────────────────────────────────────────────────────────

it('lists license types with pagination', function () {
    LicenseType::factory()->forModule(ModuleType::CONTACTS)->count(3)->create();
    LicenseType::factory()->forModule(ModuleType::COMPANIES)->count(2)->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('metadata/license-types'));

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            ['id', 'name', 'slug', 'description', 'module_type', 'is_active', 'position'],
        ],
        'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        'links',
    ]);

    expect($response->json('meta.total'))->toBe(5);
});

it('creates a license type and returns 201 with auto-generated slug', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/license-types'), [
            'name' => 'General Contractor',
            'description' => 'State general contractor license.',
            'module_type' => ModuleType::CONTACTS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'General Contractor');
    $response->assertJsonPath('data.slug', 'general-contractor');
    $response->assertJsonPath('data.module_type', 'contacts');
    $response->assertJsonPath('data.is_active', true);
    $response->assertJsonPath('data.position', 0);
});

it('shows a single license type', function () {
    $licenseType = LicenseType::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create(['name' => 'Electrical License']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("metadata/license-types/{$licenseType->id}"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $licenseType->id);
    $response->assertJsonPath('data.name', 'Electrical License');
});

it('partially updates a license type', function () {
    $licenseType = LicenseType::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create(['name' => 'Original', 'description' => 'Original description.']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/license-types/{$licenseType->id}"), [
            'name' => 'Updated Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Updated Name');
    $response->assertJsonPath('data.description', 'Original description.');
});

it('deletes a license type and returns 204', function () {
    $licenseType = LicenseType::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/license-types/{$licenseType->id}"));

    $response->assertNoContent();
    expect(LicenseType::find($licenseType->id))->toBeNull();
});

// ── Business Rules ──────────────────────────────────────────────────────────

it('ignores module_type in update payload', function () {
    $licenseType = LicenseType::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/license-types/{$licenseType->id}"), [
            'module_type' => ModuleType::COMPANIES->value,
            'name' => 'Renamed',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.module_type', 'contacts');
    $response->assertJsonPath('data.name', 'Renamed');
});

it('allows same name in different modules', function () {
    LicenseType::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create(['name' => 'General License']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/license-types'), [
            'name' => 'General License',
            'module_type' => ModuleType::COMPANIES->value,
        ]);

    $response->assertCreated();
});

// ── Validation ──────────────────────────────────────────────────────────────

it('rejects invalid module_type', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/license-types'), [
            'name' => 'Bad Module',
            'module_type' => 'invalid_module',
        ]);

    assertHasValidationError($response, 'module_type');
});

it('rejects unsupported module_type', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/license-types'), [
            'name' => 'Wrong Module',
            'module_type' => ModuleType::LEADS->value,
        ]);

    assertHasValidationError($response, 'module_type');
});

it('rejects missing required fields', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/license-types'), []);

    $response->assertUnprocessable();
});

it('rejects duplicate name within same module', function () {
    LicenseType::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create(['name' => 'Duplicate Name']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/license-types'), [
            'name' => 'Duplicate Name',
            'module_type' => ModuleType::CONTACTS->value,
        ]);

    $response->assertUnprocessable();
});

// ── Slug Behavior ───────────────────────────────────────────────────────────

it('auto-generates slug on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/license-types'), [
            'name' => 'My Custom License',
            'module_type' => ModuleType::CONTACTS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'my-custom-license');
});

it('keeps slug immutable on update', function () {
    $licenseType = LicenseType::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create(['name' => 'Original Name']);

    $originalSlug = $licenseType->slug;

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/license-types/{$licenseType->id}"), [
            'name' => 'Completely New Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.slug', $originalSlug);
});

it('generates unique slug per module type', function () {
    LicenseType::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create(['name' => 'General']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/license-types'), [
            'name' => 'General',
            'module_type' => ModuleType::COMPANIES->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'general');
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
        ->getJson($this->tenantApiUrl('metadata/license-types'));

    $response->assertForbidden();
});

it('denies create without metadata.manage permission', function () {
    $viewer = $this->createTenantUser([], ['user']);

    $response = $this->actingAs($viewer, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/license-types'), [
            'name' => 'Unauthorized',
            'module_type' => ModuleType::CONTACTS->value,
        ]);

    $response->assertForbidden();
});

it('denies delete without metadata.delete permission', function () {
    $viewer = $this->createTenantUser([], ['user']);

    $licenseType = LicenseType::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create();

    $response = $this->actingAs($viewer, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/license-types/{$licenseType->id}"));

    $response->assertForbidden();
});
