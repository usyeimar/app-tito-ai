<?php

use App\Enums\ModuleType;
use App\Models\Central\Auth\Role\Role;
use App\Models\Tenant\CRM\Leads\Lead;
use App\Models\Tenant\Metadata\Source\Source;

beforeEach(function () {
    Source::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Default Lead Source']);
});

// ── CRUD ────────────────────────────────────────────────────────────────────

it('lists sources with pagination', function () {
    Source::factory()
        ->forModule(ModuleType::LEADS)
        ->count(2)
        ->create();

    Source::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create(['name' => 'Contact Source']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('metadata/sources'));

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            ['id', 'name', 'slug', 'module_type', 'is_active'],
        ],
        'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        'links',
    ]);

    // 1 default from beforeEach + 2 leads + 1 contact = 4 total
    expect($response->json('meta.total'))->toBe(4);
    expect(count($response->json('data')))->toBe(4);
});

it('creates a source and returns 201 with auto-generated slug', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/sources'), [
            'name' => 'Referral Source',
            'module_type' => ModuleType::LEADS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Referral Source');
    $response->assertJsonPath('data.slug', 'referral-source');
    $response->assertJsonPath('data.module_type', 'leads');
    $response->assertJsonPath('data.is_active', true);
    $response->assertJsonPath('data.position', 0);
});

it('shows a single source', function () {
    $source = Source::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Test Source']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("metadata/sources/{$source->id}"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $source->id);
    $response->assertJsonPath('data.name', 'Test Source');
    $response->assertJsonPath('data.slug', $source->slug);
});

it('partially updates a source', function () {
    $source = Source::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Original', 'description' => 'Old description']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/sources/{$source->id}"), [
            'name' => 'Updated Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Updated Name');
    $response->assertJsonPath('data.description', 'Old description');
});

it('deletes a source and returns 204', function () {
    $source = Source::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Source to Delete']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/sources/{$source->id}"));

    $response->assertNoContent();

    expect(Source::find($source->id))->toBeNull();
});

it('prevents deleting a source referenced by a lead', function () {
    $source = Source::factory()
        ->forModule(ModuleType::LEADS)
        ->create();

    Lead::factory()->create(['source_id' => $source->id]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/sources/{$source->id}"));

    assertHasValidationError($response, 'record');

    expect(Source::find($source->id))->not->toBeNull();
});

// ── Validation ──────────────────────────────────────────────────────────────

it('rejects invalid module_type', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/sources'), [
            'name' => 'Bad Module',
            'module_type' => 'invalid_module',
        ]);

    assertHasValidationError($response, 'module_type');
});

it('rejects missing required fields', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/sources'), []);

    $response->assertUnprocessable();
});

it('rejects duplicate name within same module', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/sources'), [
            'name' => 'Default Lead Source',
            'module_type' => ModuleType::LEADS->value,
        ]);

    $response->assertUnprocessable();
});

// ── Slug Behavior ───────────────────────────────────────────────────────────

it('auto-generates slug on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/sources'), [
            'name' => 'My Custom Source',
            'module_type' => ModuleType::LEADS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'my-custom-source');
});

it('keeps slug immutable on update', function () {
    $source = Source::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Original Name']);

    $originalSlug = $source->slug;

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/sources/{$source->id}"), [
            'name' => 'Completely New Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.slug', $originalSlug);
});

it('generates unique slug per module type with suffix on collision', function () {
    Source::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Website', 'module_type' => ModuleType::LEADS]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/sources'), [
            'name' => 'Website',
            'module_type' => ModuleType::CONTACTS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'website');
});

// ── Icon and Position ───────────────────────────────────────────────────────

it('accepts optional icon field', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/sources'), [
            'name' => 'Marketing',
            'module_type' => ModuleType::LEADS->value,
            'icon' => 'campaign',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.icon', 'campaign');
});

it('accepts position field', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/sources'), [
            'name' => 'Direct',
            'module_type' => ModuleType::LEADS->value,
            'position' => 2,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.position', 2);
});

// ── module_type Immutability ────────────────────────────────────────────────

it('ignores module_type in update payload', function () {
    $source = Source::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Original']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/sources/{$source->id}"), [
            'module_type' => ModuleType::CONTACTS->value,
            'name' => 'Renamed',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.module_type', 'leads');
    $response->assertJsonPath('data.name', 'Renamed');
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
        ->getJson($this->tenantApiUrl('metadata/sources'));

    $response->assertForbidden();
});

it('denies create without metadata.manage permission', function () {
    $viewer = $this->createTenantUser([], ['user']);

    $response = $this->actingAs($viewer, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/sources'), [
            'name' => 'Unauthorized',
            'module_type' => ModuleType::LEADS->value,
        ]);

    $response->assertForbidden();
});

it('denies delete without metadata.delete permission', function () {
    $viewer = $this->createTenantUser([], ['user']);

    $source = Source::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'To Delete']);

    $response = $this->actingAs($viewer, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/sources/{$source->id}"));

    $response->assertForbidden();
});
