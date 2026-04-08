<?php

use App\Actions\Tenant\Metadata\ResourceType\DeleteResourceType;
use App\Enums\ModuleType;
use App\Models\Central\Auth\Role\Role;
use App\Models\Tenant\Metadata\ResourceType\ResourceType;
use App\Services\Tenant\Commons\ProfilePictures\ProfilePictureService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// ── CRUD ────────────────────────────────────────────────────────────────────

it('lists resource types with pagination', function () {
    ResourceType::factory()
        ->forModule(ModuleType::VEHICLES)
        ->count(3)
        ->create();

    ResourceType::factory()
        ->forModule(ModuleType::EQUIPMENT)
        ->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('metadata/resource-types'));

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            ['id', 'name', 'slug', 'module_type', 'is_active', 'position', 'profile_picture'],
        ],
        'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        'links',
    ]);

    expect($response->json('meta.total'))->toBe(4);
});

it('creates a resource type and returns 201 with auto-generated slug', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/resource-types'), [
            'name' => 'Excavator',
            'module_type' => ModuleType::EQUIPMENT->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Excavator');
    $response->assertJsonPath('data.slug', 'excavator');
    $response->assertJsonPath('data.module_type', 'equipment');
    $response->assertJsonPath('data.is_active', true);
});

it('shows a single resource type', function () {
    $resourceType = ResourceType::factory()
        ->forModule(ModuleType::VEHICLES)
        ->create(['name' => 'Truck']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("metadata/resource-types/{$resourceType->id}"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $resourceType->id);
    $response->assertJsonPath('data.name', 'Truck');
});

it('partially updates a resource type', function () {
    $resourceType = ResourceType::factory()
        ->forModule(ModuleType::VEHICLES)
        ->create(['name' => 'Original']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/resource-types/{$resourceType->id}"), [
            'name' => 'Updated Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Updated Name');
});

it('deletes a resource type and returns 204', function () {
    $resourceType = ResourceType::factory()
        ->forModule(ModuleType::VEHICLES)
        ->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/resource-types/{$resourceType->id}"));

    $response->assertNoContent();
    expect(ResourceType::find($resourceType->id))->toBeNull();
});

it('continues resource type deletion when profile picture cleanup fails', function () {
    $resourceType = ResourceType::factory()
        ->forModule(ModuleType::VEHICLES)
        ->create();

    $resourceType->profilePicture()->create([
        'path' => 'profile-images/resource_type/test.webp',
    ]);

    $pictureService = Mockery::mock(ProfilePictureService::class);
    $pictureService
        ->shouldReceive('delete')
        ->once()
        ->with('profile-images/resource_type/test.webp')
        ->andThrow(new RuntimeException('Cleanup failed'));

    Log::shouldReceive('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($resourceType): bool {
            return $message === 'Resource type profile picture cleanup failed after deletion.'
                && isset($context['resource_type_id'], $context['path'], $context['error'])
                && (string) $context['resource_type_id'] === (string) $resourceType->getKey()
                && $context['path'] === 'profile-images/resource_type/test.webp'
                && str_contains((string) $context['error'], 'Cleanup failed');
        });

    $action = new DeleteResourceType($pictureService);

    $action($resourceType);

    expect(ResourceType::query()->whereKey($resourceType->getKey())->exists())->toBeFalse();
});

// ── Business Rules ──────────────────────────────────────────────────────────

it('ignores module_type in update payload', function () {
    $resourceType = ResourceType::factory()
        ->forModule(ModuleType::VEHICLES)
        ->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/resource-types/{$resourceType->id}"), [
            'module_type' => ModuleType::EQUIPMENT->value,
            'name' => 'Renamed',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.module_type', 'vehicles');
    $response->assertJsonPath('data.name', 'Renamed');
});

it('creates a resource type with quantity and rate fields', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/resource-types'), [
            'name' => 'Heavy Operator',
            'module_type' => ModuleType::USERS->value,
            'suggested_quantity' => 3,
            'minimum_quantity' => 1,
            'maximum_quantity' => 5,
            'suggested_rate' => 95.50,
            'rate_type' => 'HOUR',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.suggested_quantity', '3.0000');
    $response->assertJsonPath('data.minimum_quantity', '1.0000');
    $response->assertJsonPath('data.maximum_quantity', '5.0000');
    $response->assertJsonPath('data.suggested_rate', '95.5000');
    $response->assertJsonPath('data.rate_type', 'HOUR');
});

// ── Quantity Constraints ────────────────────────────────────────────────────

it('rejects min > max on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/resource-types'), [
            'name' => 'Bad Quantities',
            'module_type' => ModuleType::VEHICLES->value,
            'minimum_quantity' => 10,
            'maximum_quantity' => 5,
        ]);

    assertHasValidationError($response, 'minimum_quantity');
});

it('rejects suggested < min on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/resource-types'), [
            'name' => 'Bad Suggested Low',
            'module_type' => ModuleType::VEHICLES->value,
            'suggested_quantity' => 1,
            'minimum_quantity' => 5,
            'maximum_quantity' => 10,
        ]);

    assertHasValidationError($response, 'suggested_quantity');
});

it('rejects suggested > max on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/resource-types'), [
            'name' => 'Bad Suggested High',
            'module_type' => ModuleType::VEHICLES->value,
            'suggested_quantity' => 20,
            'minimum_quantity' => 1,
            'maximum_quantity' => 10,
        ]);

    assertHasValidationError($response, 'suggested_quantity');
});

it('rejects min > max on update', function () {
    $resourceType = ResourceType::factory()
        ->forModule(ModuleType::VEHICLES)
        ->withQuantities(suggested: 3, min: 1, max: 5)
        ->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/resource-types/{$resourceType->id}"), [
            'minimum_quantity' => 10,
        ]);

    assertHasValidationError($response, 'maximum_quantity');
});

// ── Validation ──────────────────────────────────────────────────────────────

it('rejects invalid module_type', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/resource-types'), [
            'name' => 'Bad Module',
            'module_type' => 'invalid_module',
        ]);

    assertHasValidationError($response, 'module_type');
});

it('rejects unsupported module_type for resource types', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/resource-types'), [
            'name' => 'Bad Module',
            'module_type' => ModuleType::CONTACTS->value,
        ]);

    assertHasValidationError($response, 'module_type');
});

it('rejects missing required fields', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/resource-types'), []);

    $response->assertUnprocessable();
});

it('rejects duplicate name within same module', function () {
    ResourceType::factory()
        ->forModule(ModuleType::VEHICLES)
        ->create(['name' => 'Duplicate Name']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/resource-types'), [
            'name' => 'Duplicate Name',
            'module_type' => ModuleType::VEHICLES->value,
        ]);

    $response->assertUnprocessable();
});

it('allows same name in different modules', function () {
    ResourceType::factory()
        ->forModule(ModuleType::VEHICLES)
        ->create(['name' => 'Operator']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/resource-types'), [
            'name' => 'Operator',
            'module_type' => ModuleType::EQUIPMENT->value,
        ]);

    $response->assertCreated();
});

it('rejects invalid rate_type', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/resource-types'), [
            'name' => 'Bad Rate',
            'module_type' => ModuleType::VEHICLES->value,
            'rate_type' => 'WEEK',
        ]);

    assertHasValidationError($response, 'rate_type');
});

// ── Slug Behavior ───────────────────────────────────────────────────────────

it('auto-generates slug on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/resource-types'), [
            'name' => 'Heavy Duty Truck',
            'module_type' => ModuleType::VEHICLES->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'heavy-duty-truck');
});

it('keeps slug immutable on update', function () {
    $resourceType = ResourceType::factory()
        ->forModule(ModuleType::VEHICLES)
        ->create(['name' => 'Original Name']);

    $originalSlug = $resourceType->slug;

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("metadata/resource-types/{$resourceType->id}"), [
            'name' => 'Completely New Name',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.slug', $originalSlug);
});

it('generates unique slug per module type', function () {
    ResourceType::factory()
        ->forModule(ModuleType::VEHICLES)
        ->create(['name' => 'Standard']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/resource-types'), [
            'name' => 'Standard',
            'module_type' => ModuleType::EQUIPMENT->value,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.slug', 'standard');
});

// ── Profile Picture ─────────────────────────────────────────────────────────

it('creates a resource type with profile picture', function () {
    Storage::fake(config('filesystems.default'));

    $response = $this->actingAs($this->user, 'tenant-api')
        ->post($this->tenantApiUrl('metadata/resource-types'), [
            'name' => 'With Image',
            'module_type' => ModuleType::VEHICLES->value,
            'profile_picture' => UploadedFile::fake()->image('truck.jpg', 200, 200),
        ], ['Accept' => 'application/json']);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'With Image');
    expect($response->json('data.profile_picture'))->not->toBeNull()
        ->toHaveKeys(['id', 'url']);
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
        ->getJson($this->tenantApiUrl('metadata/resource-types'));

    $response->assertForbidden();
});

it('denies create without metadata.manage permission', function () {
    $viewer = $this->createTenantUser([], ['user']);

    $response = $this->actingAs($viewer, 'tenant-api')
        ->postJson($this->tenantApiUrl('metadata/resource-types'), [
            'name' => 'Unauthorized',
            'module_type' => ModuleType::VEHICLES->value,
        ]);

    $response->assertForbidden();
});

it('denies delete without metadata.delete permission', function () {
    $viewer = $this->createTenantUser([], ['user']);

    $resourceType = ResourceType::factory()
        ->forModule(ModuleType::VEHICLES)
        ->create();

    $response = $this->actingAs($viewer, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("metadata/resource-types/{$resourceType->id}"));

    $response->assertForbidden();
});
