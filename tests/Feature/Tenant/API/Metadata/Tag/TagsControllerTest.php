<?php

use App\Enums\ModuleType;
use App\Models\Central\Auth\Role\Role;
use App\Models\Tenant\Metadata\Tag\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// ── CRUD ────────────────────────────────────────────────────────────────────

it('lists tags with pagination', function () {
    $perPage = 100;

    Tag::factory()
        ->forModule(ModuleType::LEADS)
        ->count(3)
        ->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("metadata/tags?page[size]={$perPage}"));

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'name', 'slug', 'color', 'module_type', 'is_active', 'position']],
        'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        'links',
    ]);
    $response->assertJsonPath('meta.current_page', 1);
    $response->assertJsonPath('meta.per_page', $perPage);
    $response->assertJsonPath('meta.total', 3);
    $response->assertJsonCount(3, 'data');
});

it('creates a tag and returns 201 with auto-generated slug', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/tags'), [
            'name' => 'High Value',
            'color' => '#22C55E',
            'module_type' => ModuleType::LEADS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'High Value');
    $response->assertJsonPath('data.slug', 'high-value');
    $response->assertJsonPath('data.module_type', 'leads');
    $response->assertJsonPath('data.is_active', true);
    $response->assertJsonPath('data.position', 0);
});

it('shows a single tag', function () {
    $tag = Tag::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'VIP', 'color' => '#AABBCC']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("metadata/tags/{$tag->id}"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $tag->id);
    $response->assertJsonPath('data.name', 'VIP');
    $response->assertJsonPath('data.slug', 'vip');
});

it('partially updates a tag', function () {
    $tag = Tag::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Original', 'color' => '#000000']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/tags/{$tag->id}"), [
            'name' => 'Updated Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Updated Name');
    $response->assertJsonPath('data.color', '#000000');
});

it('deletes a tag and returns 204', function () {
    $tag = Tag::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Disposable', 'color' => '#AABBCC']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/tags/{$tag->id}"));

    $response->assertNoContent();
    expect(Tag::query()->whereKey($tag->id)->exists())->toBeFalse();
});

// ── Business Rules ──────────────────────────────────────────────────────────

it('ignores module_type in update payload', function () {
    $tag = Tag::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['color' => '#AABBCC']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/tags/{$tag->id}"), [
            'module_type' => ModuleType::COMPANIES->value,
            'name' => 'Renamed',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.module_type', 'leads');
    $response->assertJsonPath('data.name', 'Renamed');
});

it('cascade-deletes taggable pivot entries when tag is deleted', function () {
    $tag = Tag::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['color' => '#AABBCC']);

    // Insert a pivot entry manually
    DB::table('metadata_taggables')->insert([
        'id' => Str::ulid()->toBase32(),
        'tag_id' => $tag->id,
        'taggable_type' => 'App\\Models\\Tenant\\CRM\\Leads\\Lead',
        'taggable_id' => Str::ulid()->toBase32(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('metadata_taggables')->where('tag_id', $tag->id)->count())->toBe(1);

    $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/tags/{$tag->id}"))
        ->assertNoContent();

    expect(DB::table('metadata_taggables')->where('tag_id', $tag->id)->count())->toBe(0);
});

// ── Slug Behavior ───────────────────────────────────────────────────────────

it('auto-generates slug on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/tags'), [
            'name' => 'My Custom Tag',
            'color' => '#AABBCC',
            'module_type' => ModuleType::LEADS->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'my-custom-tag');
});

it('keeps slug immutable on update', function () {
    $tag = Tag::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Original Name', 'color' => '#AABBCC']);

    $originalSlug = $tag->slug;

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/tags/{$tag->id}"), [
            'name' => 'Completely New Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.slug', $originalSlug);
});

it('generates unique slug per module type with suffix on collision', function () {
    Tag::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Active', 'color' => '#AABBCC']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/tags'), [
            'name' => 'Active',
            'color' => '#BBCCDD',
            'module_type' => ModuleType::COMPANIES->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'active');
});

// ── Validation ──────────────────────────────────────────────────────────────

it('rejects invalid hex color', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/tags'), [
            'name' => 'Bad Color',
            'color' => 'not-a-hex',
            'module_type' => ModuleType::LEADS->value,
        ]);

    assertHasValidationError($response, 'color');
});

it('rejects invalid module_type', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/tags'), [
            'name' => 'Bad Module',
            'color' => '#AABBCC',
            'module_type' => 'invalid_module',
        ]);

    assertHasValidationError($response, 'module_type');
});

it('rejects missing required fields', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/tags'), []);

    $response->assertUnprocessable();
});

it('rejects duplicate name within same module', function () {
    Tag::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['name' => 'Duplicate Tag', 'color' => '#AABBCC']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/tags'), [
            'name' => 'Duplicate Tag',
            'color' => '#AABBCC',
            'module_type' => ModuleType::LEADS->value,
        ]);

    assertHasValidationError($response, 'name');
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
        ->getJson($this->tenantApiUrl('metadata/tags'));

    $response->assertForbidden();
});

it('denies create without metadata.manage permission', function () {
    $viewer = $this->createTenantUser([], ['user']);

    $response = $this->actingAs($viewer, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/tags'), [
            'name' => 'Unauthorized',
            'color' => '#AABBCC',
            'module_type' => ModuleType::LEADS->value,
        ]);

    $response->assertForbidden();
});

it('denies delete without metadata.delete permission', function () {
    $viewer = $this->createTenantUser([], ['user']);

    $tag = Tag::factory()
        ->forModule(ModuleType::LEADS)
        ->create(['color' => '#FF0000']);

    $response = $this->actingAs($viewer, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/tags/{$tag->id}"));

    $response->assertForbidden();
});
