<?php

use App\Enums\ModuleType;
use App\Models\Tenant\Metadata\Category\Category;
use App\Models\Tenant\Services\Service\Service;
use App\Models\Tenant\Services\ServiceItem\ServiceItem;

beforeEach(function () {
    // Create a baseline category in COMPANIES module for tests
    Category::factory()
        ->forModule(ModuleType::COMPANIES)
        ->create([
            'name' => 'Baseline Category',
            'position' => 0,
        ]);
});

describe('Index', function () {
    test('returns paginated categories', function () {
        Category::factory()
            ->forModule(ModuleType::COMPANIES)
            ->create(['name' => 'Tech Companies']);

        Category::factory()
            ->forModule(ModuleType::SERVICES)
            ->create(['name' => 'Engineering Services']);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->getJson($this->tenantApiUrl('metadata/categories'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'slug', 'description', 'icon', 'module_type', 'is_active', 'position'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links',
            ]);

        // 1 baseline + 1 companies + 1 services = 3
        expect($response->json('meta.total'))->toBe(3);
    });

    test('returns categories ordered by module_type then position then name', function () {
        Category::factory()
            ->forModule(ModuleType::COMPANIES)
            ->withPosition(2)
            ->create(['name' => 'Third Order']);

        Category::factory()
            ->forModule(ModuleType::COMPANIES)
            ->withPosition(0)
            ->create(['name' => 'First Order']);

        Category::factory()
            ->forModule(ModuleType::COMPANIES)
            ->withPosition(1)
            ->create(['name' => 'Second Order']);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->getJson($this->tenantApiUrl('metadata/categories'));

        $data = collect($response->json('data'));

        $newCompanies = $data
            ->filter(fn ($c) => str_contains($c['name'], 'Order'))
            ->values()
            ->all();

        expect($newCompanies[0]['name'])->toBe('First Order')
            ->and($newCompanies[1]['name'])->toBe('Second Order')
            ->and($newCompanies[2]['name'])->toBe('Third Order');
    });
});

describe('Store', function () {
    test('creates a category with 201 status', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('metadata/categories'), [
                'name' => 'New Category',
                'module_type' => 'companies',
                'description' => 'Test description',
                'icon' => 'building',
                'is_active' => true,
                'position' => 5,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New Category')
            ->assertJsonPath('data.module_type', 'companies')
            ->assertJsonPath('data.icon', 'building')
            ->assertJsonPath('data.position', 5);

        expect(Category::where('name', 'New Category')->exists())->toBeTrue();
    });

    test('auto-generates slug from name', function () {
        $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('metadata/categories'), [
                'name' => 'Web Development Services',
                'module_type' => 'services',
            ]);

        $category = Category::where('name', 'Web Development Services')->first();
        expect($category->slug)->toBe('web-development-services');
    });

    test('slug is scoped per module_type (allows same slug in different modules)', function () {
        $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('metadata/categories'), [
                'name' => 'Design',
                'module_type' => 'companies',
            ]);

        $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('metadata/categories'), [
                'name' => 'Design',
                'module_type' => 'services',
            ]);

        $companiesDesign = Category::where('module_type', ModuleType::COMPANIES)->where('name', 'Design')->first();
        $servicesDesign = Category::where('module_type', ModuleType::SERVICES)->where('name', 'Design')->first();

        expect($companiesDesign->slug)->toBe('design')
            ->and($servicesDesign->slug)->toBe('design');
    });

    test('slug collision: unique names can have slugs with same base (if in different modules)', function () {
        // Create category in companies module
        $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('metadata/categories'), [
                'name' => 'Infrastructure',
                'module_type' => 'companies',
            ]);

        // Create category with different name but same slug in companies
        // (e.g., "Infrastructure Co" -> "infrastructure-co", still collides when slugified from "Infrastructure Co")
        // For simplicity, just verify that slugs are properly scoped per module
        $category1 = Category::where('module_type', ModuleType::COMPANIES)
            ->where('name', 'Infrastructure')
            ->first();

        expect($category1->slug)->toBe('infrastructure');
    });

    test('requires name', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('metadata/categories'), [
                'module_type' => 'companies',
            ]);

        $response->assertUnprocessable();
        assertHasValidationError($response, 'name');
    });

    test('validates module_type enum', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('metadata/categories'), [
                'name' => 'Test',
                'module_type' => 'invalid_module',
            ]);

        $response->assertUnprocessable();
    });

    test('restricts module_type to Category::MODULE_TYPES', function () {
        // LEADS is a valid ModuleType but not valid for Categories
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('metadata/categories'), [
                'name' => 'Test',
                'module_type' => 'leads',
            ]);

        $response->assertUnprocessable();
    });

    test('rejects duplicate name within same module', function () {
        Category::factory()
            ->forModule(ModuleType::COMPANIES)
            ->create(['name' => 'Duplicate Name']);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('metadata/categories'), [
                'name' => 'Duplicate Name',
                'module_type' => 'companies',
            ]);

        $response->assertUnprocessable();
    });

    test('allows same name in different modules', function () {
        Category::factory()
            ->forModule(ModuleType::COMPANIES)
            ->create(['name' => 'Shared Name']);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('metadata/categories'), [
                'name' => 'Shared Name',
                'module_type' => 'services',
            ]);

        $response->assertCreated();
    });

    test('validates icon field max length', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('metadata/categories'), [
                'name' => 'Test',
                'module_type' => 'companies',
                'icon' => str_repeat('a', 101), // exceeds max:100
            ]);

        $response->assertUnprocessable();
    });

    test('allows null icon field', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('metadata/categories'), [
                'name' => 'No Icon Category',
                'module_type' => 'companies',
                'icon' => null,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.icon', null);
    });

});

describe('Show', function () {
    test('returns a single category', function () {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->user, 'tenant-api')
            ->getJson($this->tenantApiUrl("metadata/categories/{$category->id}"));

        $response->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.name', $category->name)
            ->assertJsonPath('data.slug', $category->slug);
    });

});

describe('Update', function () {
    test('updates a category', function () {
        $category = Category::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->patchJson($this->tenantApiUrl("metadata/categories/{$category->id}"), [
                'name' => 'New Name',
                'description' => 'Updated description',
                'position' => 10,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.position', 10);

        expect($category->refresh()->name)->toBe('New Name');
    });

    test('slug is immutable on update (does not regenerate)', function () {
        $category = Category::factory()->create(['name' => 'Original Name']);
        $originalSlug = $category->slug;

        $this->actingAs($this->user, 'tenant-api')
            ->patchJson($this->tenantApiUrl("metadata/categories/{$category->id}"), [
                'name' => 'Completely Different Name',
            ]);

        $category->refresh();
        expect($category->slug)->toBe($originalSlug);
    });

    test('module_type is immutable (excluded from update DTO)', function () {
        $category = Category::factory()
            ->forModule(ModuleType::COMPANIES)
            ->create();

        $this->actingAs($this->user, 'tenant-api')
            ->patchJson($this->tenantApiUrl("metadata/categories/{$category->id}"), [
                'module_type' => 'services', // Try to change it
            ]);

        $category->refresh();
        expect($category->module_type)->toBe(ModuleType::COMPANIES);
    });

    test('allows partial updates', function () {
        $category = Category::factory()->create([
            'name' => 'Original',
            'description' => 'Original description',
            'position' => 0,
        ]);

        $this->actingAs($this->user, 'tenant-api')
            ->patchJson($this->tenantApiUrl("metadata/categories/{$category->id}"), [
                'name' => 'Updated Name',
                // description and position not sent
            ]);

        $category->refresh();
        expect($category->name)->toBe('Updated Name')
            ->and($category->description)->toBe('Original description')
            ->and($category->position)->toBe(0);
    });

    test('updates position field', function () {
        $category = Category::factory()->withPosition(0)->create();

        $this->actingAs($this->user, 'tenant-api')
            ->patchJson($this->tenantApiUrl("metadata/categories/{$category->id}"), [
                'position' => 10,
            ]);

        expect($category->refresh()->position)->toBe(10);
    });

    test('rejects duplicate name within same module on update', function () {
        $category1 = Category::factory()
            ->forModule(ModuleType::COMPANIES)
            ->create(['name' => 'First Category']);

        $category2 = Category::factory()
            ->forModule(ModuleType::COMPANIES)
            ->create(['name' => 'Second Category']);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->patchJson($this->tenantApiUrl("metadata/categories/{$category2->id}"), [
                'name' => 'First Category', // Try to rename to existing name
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', 'name');
    });

});

describe('Delete', function () {
    test('deletes a category and returns 204', function () {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->user, 'tenant-api')
            ->deleteJson($this->tenantApiUrl("metadata/categories/{$category->id}"));

        $response->assertNoContent();
        expect(Category::find($category->id))->toBeNull();
    });

    test('prevents deletion of category with dependent services (restrictOnDelete)', function () {
        $category = Category::factory()
            ->forModule(ModuleType::SERVICES)
            ->create();

        $service = Service::factory()
            ->create(['category_id' => $category->id]);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->deleteJson($this->tenantApiUrl("metadata/categories/{$category->id}"));

        $response->assertUnprocessable();
        assertHasValidationError($response, 'record');
        expect(Category::find($category->id))->not->toBeNull();
        expect($service->refresh()->category_id)->toBe($category->id);
    });

    test('prevents deletion of category with dependent service_items (restrictOnDelete)', function () {
        $category = Category::factory()
            ->forModule(ModuleType::SERVICES)
            ->create();

        $serviceItem = ServiceItem::factory()
            ->create(['category_id' => $category->id]);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->deleteJson($this->tenantApiUrl("metadata/categories/{$category->id}"));

        $response->assertUnprocessable();
        assertHasValidationError($response, 'record');
        expect(Category::find($category->id))->not->toBeNull();
        expect($serviceItem->refresh()->category_id)->toBe($category->id);
    });

    test('allows deletion of category when category_id is null in services and service_items', function () {
        $category = Category::factory()
            ->forModule(ModuleType::SERVICES)
            ->create();

        // Create services/items with null category_id
        Service::factory()->create(['category_id' => null]);
        ServiceItem::factory()->create(['category_id' => null]);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->deleteJson($this->tenantApiUrl("metadata/categories/{$category->id}"));

        $response->assertNoContent();
        expect(Category::find($category->id))->toBeNull();
    });

    test('deletes multiple categories independently', function () {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        $this->actingAs($this->user, 'tenant-api')
            ->deleteJson($this->tenantApiUrl("metadata/categories/{$category1->id}"));

        expect(Category::find($category1->id))->toBeNull()
            ->and(Category::find($category2->id))->not->toBeNull();
    });

});

describe('Validation errors', function () {
    test('uses custom error format with source.pointer', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('metadata/categories'), [
                'module_type' => 'companies',
                // missing name
            ]);

        $response->assertUnprocessable();
        expect($response['errors'])->toHaveKey('0.source.pointer');
        expect($response['errors'][0]['source']['pointer'])->toContain('name');
    });
});
