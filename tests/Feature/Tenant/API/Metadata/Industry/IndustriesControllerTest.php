<?php

use App\Enums\ModuleType;
use App\Models\Central\Auth\Role\Role;
use App\Models\Tenant\Metadata\Category\Category;
use App\Models\Tenant\Metadata\Industry\Industriable;
use App\Models\Tenant\Metadata\Industry\Industry;
use Illuminate\Support\Str;

// ── CRUD ────────────────────────────────────────────────────────────────────

it('lists industries with pagination', function () {
    $category = Category::factory()->create();
    $perPage = 100;

    Industry::factory()
        ->forCategory($category)
        ->count(3)
        ->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("metadata/industries?page[size]={$perPage}"));

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'name', 'slug', 'description', 'icon', 'category_id', 'is_active', 'position']],
        'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        'links',
    ]);
    $response->assertJsonPath('meta.current_page', 1);
    $response->assertJsonPath('meta.per_page', $perPage);
    $response->assertJsonPath('meta.total', 3);
    $response->assertJsonCount(3, 'data');
});

it('creates an industry and returns 201', function () {
    $category = Category::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/industries'), [
            'name' => 'Residential Construction',
            'category_id' => $category->id,
            'description' => 'Residential builders.',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Residential Construction');
    $response->assertJsonPath('data.slug', 'residential-construction');
    $response->assertJsonPath('data.category_id', $category->id);
    $response->assertJsonPath('data.is_active', true);
    $response->assertJsonPath('data.position', 0);
});

it('shows a single industry', function () {
    $category = Category::factory()->create();
    $industry = Industry::factory()
        ->forCategory($category)
        ->create(['name' => 'Electrical']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("metadata/industries/{$industry->id}"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $industry->id);
    $response->assertJsonPath('data.name', 'Electrical');
    $response->assertJsonPath('data.slug', 'electrical');
});

it('partially updates an industry', function () {
    $category = Category::factory()->create();
    $industry = Industry::factory()
        ->forCategory($category)
        ->create(['name' => 'Original', 'description' => 'Old desc']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/industries/{$industry->id}"), [
            'name' => 'Updated Name',
            'position' => 5,
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Updated Name');
    $response->assertJsonPath('data.description', 'Old desc');
    $response->assertJsonPath('data.position', 5);
});

it('deletes an industry and returns 204', function () {
    $category = Category::factory()->create();
    $industry = Industry::factory()
        ->forCategory($category)
        ->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/industries/{$industry->id}"));

    $response->assertNoContent();
    expect(Industry::query()->whereKey($industry->id)->exists())->toBeFalse();
});

// ── Slug Behavior ───────────────────────────────────────────────────────────

it('auto-generates slug on create', function () {
    $category = Category::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/industries'), [
            'name' => 'HVAC Systems',
            'category_id' => $category->id,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'hvac-systems');
});

it('keeps slug immutable on update', function () {
    $category = Category::factory()->create();
    $industry = Industry::factory()
        ->forCategory($category)
        ->create(['name' => 'Original Name']);

    $originalSlug = $industry->slug;

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/industries/{$industry->id}"), [
            'name' => 'Completely New Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.slug', $originalSlug);
});

it('scopes slug uniqueness per category', function () {
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();

    Industry::factory()
        ->forCategory($categoryA)
        ->create(['name' => 'Plumbing']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/industries'), [
            'name' => 'Plumbing',
            'category_id' => $categoryB->id,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'plumbing');
});

// ── Validation ──────────────────────────────────────────────────────────────

it('requires name to create', function () {
    $category = Category::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/industries'), [
            'category_id' => $category->id,
        ]);

    assertHasValidationError($response, 'name');
});

it('requires category_id to create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/industries'), [
            'name' => 'Missing Category',
        ]);

    assertHasValidationError($response, 'category_id');
});

it('rejects invalid category_id', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/industries'), [
            'name' => 'Bad Category',
            'category_id' => '01JNONEXISTENT0000000000000',
        ]);

    assertHasValidationError($response, 'category_id');
});

it('validates icon max length', function () {
    $category = Category::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/industries'), [
            'name' => 'Long Icon',
            'category_id' => $category->id,
            'icon' => str_repeat('x', 101),
        ]);

    assertHasValidationError($response, 'icon');
});

it('rejects duplicate name within same category', function () {
    $category = Category::factory()->create();

    Industry::factory()
        ->forCategory($category)
        ->create(['name' => 'Duplicate']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/industries'), [
            'name' => 'Duplicate',
            'category_id' => $category->id,
        ]);

    $response->assertUnprocessable();
    assertHasValidationError($response, 'name');
});

// ── Category Scoping ────────────────────────────────────────────────────────

it('allows same name in different categories', function () {
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();

    Industry::factory()
        ->forCategory($categoryA)
        ->create(['name' => 'General']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/industries'), [
            'name' => 'General',
            'category_id' => $categoryB->id,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'General');
});

// ── Immutability ────────────────────────────────────────────────────────────

it('ignores category_id on update', function () {
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();

    $industry = Industry::factory()
        ->forCategory($categoryA)
        ->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/industries/{$industry->id}"), [
            'category_id' => $categoryB->id,
            'name' => 'Renamed',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.category_id', $categoryA->id);
    $response->assertJsonPath('data.name', 'Renamed');
});

// ── Restrict Delete ─────────────────────────────────────────────────────────

it('prevents deleting an industry with associated records', function () {
    $category = Category::factory()->create();
    $industry = Industry::factory()
        ->forCategory($category)
        ->create();

    Industriable::query()->forceCreate([
        'id' => Str::ulid()->toBase32(),
        'industry_id' => $industry->id,
        'industriable_type' => 'App\\Models\\Tenant\\CRM\\Leads\\Lead',
        'industriable_id' => Str::ulid()->toBase32(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/industries/{$industry->id}"));

    assertHasValidationError($response, 'record');

    expect(Industry::find($industry->id))->not->toBeNull();
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
        ->getJson($this->tenantApiUrl('metadata/industries'));

    $response->assertForbidden();
});

it('denies create without metadata.manage permission', function () {
    $viewer = $this->createTenantUser([], ['user']);
    $category = Category::factory()->create();

    $response = $this->actingAs($viewer, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/industries'), [
            'name' => 'Unauthorized',
            'category_id' => $category->id,
        ]);

    $response->assertForbidden();
});

it('denies delete without metadata.delete permission', function () {
    $viewer = $this->createTenantUser([], ['user']);
    $category = Category::factory()->create();

    $industry = Industry::factory()
        ->forCategory($category)
        ->create();

    $response = $this->actingAs($viewer, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/industries/{$industry->id}"));

    $response->assertForbidden();
});

// ── Sorting ─────────────────────────────────────────────────────────────────

it('returns industries sorted by category, position, name by default', function () {
    $categoryA = Category::factory()->create([
        'name' => 'A Category',
        'module_type' => ModuleType::COMPANIES,
        'position' => 0,
    ]);
    $categoryB = Category::factory()->create([
        'name' => 'B Category',
        'module_type' => ModuleType::COMPANIES,
        'position' => 1,
    ]);

    // Create industries in reverse order to test sorting
    Industry::factory()->forCategory($categoryB)->create(['name' => 'Zebra', 'position' => 0]);
    Industry::factory()->forCategory($categoryA)->create(['name' => 'Beta', 'position' => 1]);
    Industry::factory()->forCategory($categoryA)->create(['name' => 'Alpha', 'position' => 0]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('metadata/industries?page[size]=100'));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(3);

    // First two should be from categoryA (sorted by position then name)
    expect($data[0]['name'])->toBe('Alpha');
    expect($data[1]['name'])->toBe('Beta');
    expect($data[2]['name'])->toBe('Zebra');
});
