<?php

use App\Enums\ModuleType;
use App\Models\Central\Auth\Role\Permission;
use App\Models\Tenant\System\ColumnConfiguration\SystemColumnConfiguration;

describe('Index', function () {
    test('returns paginated system column configurations', function () {
        SystemColumnConfiguration::factory()
            ->forModule(ModuleType::LEADS)
            ->create(['data' => ['columns' => ['name', 'email']]]);

        SystemColumnConfiguration::factory()
            ->forModule(ModuleType::CONTACTS)
            ->create(['data' => ['columns' => ['phone']]]);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->getJson($this->tenantApiUrl('system/column-configurations'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'module', 'data', 'created_at', 'updated_at'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links',
            ]);

        expect($response->json('meta.total'))->toBe(2);
    });

    test('denies access for regular user without permission', function () {
        $regularUser = $this->createTenantUser(roles: ['user']);

        // Ensure the user role has NO system_configurations.view permission
        Permission::query()->where('name', 'system_configurations.view')
            ->where('guard_name', 'tenant')
            ->delete();

        $response = $this->actingAs($regularUser, 'tenant-api')
            ->getJson($this->tenantApiUrl('system/column-configurations'));

        $response->assertForbidden();
    });
});

describe('Store', function () {
    test('creates a system column configuration with 201 status', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('system/column-configurations'), [
                'module' => 'leads',
                'data' => ['columns' => ['name', 'email', 'status']],
            ]);

        $response->assertCreated()
            ->assertJsonPath('module', 'leads')
            ->assertJsonPath('data.columns', ['name', 'email', 'status']);

        expect(SystemColumnConfiguration::count())->toBe(1);
    });

    test('requires module field', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('system/column-configurations'), [
                'data' => ['columns' => ['name']],
            ]);

        $response->assertUnprocessable();
        assertHasValidationError($response, 'module');
    });

    test('requires data field', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('system/column-configurations'), [
                'module' => 'leads',
            ]);

        $response->assertUnprocessable();
        assertHasValidationError($response, 'data');
    });

    test('validates module enum', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('system/column-configurations'), [
                'module' => 'invalid_module',
                'data' => ['columns' => ['name']],
            ]);

        $response->assertUnprocessable();
    });

    test('accepts metadata module and filters by it', function () {
        SystemColumnConfiguration::factory()
            ->forModule('metadata-statuses')
            ->create(['data' => ['columns' => ['name', 'slug']]]);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->getJson($this->tenantApiUrl('system/column-configurations?filter[module]=metadata-statuses&page[size]=100'));

        $response->assertOk()
            ->assertJsonPath('data.0.module', 'metadata-statuses')
            ->assertJsonPath('data.0.data.columns', ['name', 'slug']);
    });

    test('denies creation for regular user', function () {
        $regularUser = $this->createTenantUser(roles: ['user']);

        $response = $this->actingAs($regularUser, 'tenant-api')
            ->postJson($this->tenantApiUrl('system/column-configurations'), [
                'module' => 'leads',
                'data' => ['columns' => ['name']],
            ]);

        $response->assertForbidden();
    });
});

describe('Show', function () {
    test('returns a single system column configuration', function () {
        $config = SystemColumnConfiguration::factory()
            ->forModule(ModuleType::LEADS)
            ->create(['data' => ['columns' => ['name']]]);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->getJson($this->tenantApiUrl("system/column-configurations/{$config->id}"));

        $response->assertOk()
            ->assertJsonPath('id', $config->id)
            ->assertJsonPath('module', 'leads')
            ->assertJsonPath('data.columns', ['name']);
    });
});

describe('Update', function () {
    test('updates a system column configuration', function () {
        $config = SystemColumnConfiguration::factory()
            ->forModule(ModuleType::LEADS)
            ->create(['data' => ['columns' => ['name']]]);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->patchJson($this->tenantApiUrl("system/column-configurations/{$config->id}"), [
                'data' => ['columns' => ['name', 'email', 'phone']],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.columns', ['name', 'email', 'phone']);

        expect($config->refresh()->data)->toBe(['columns' => ['name', 'email', 'phone']]);
    });

    test('module is immutable on update', function () {
        $config = SystemColumnConfiguration::factory()
            ->forModule(ModuleType::LEADS)
            ->create();

        $this->actingAs($this->user, 'tenant-api')
            ->patchJson($this->tenantApiUrl("system/column-configurations/{$config->id}"), [
                'module' => 'contacts',
            ]);

        expect($config->refresh()->module)->toBe('leads');
    });
});

describe('Delete', function () {
    test('deletes a system column configuration and returns 204', function () {
        $config = SystemColumnConfiguration::factory()->create();

        $response = $this->actingAs($this->user, 'tenant-api')
            ->deleteJson($this->tenantApiUrl("system/column-configurations/{$config->id}"));

        $response->assertNoContent();
        expect(SystemColumnConfiguration::find($config->id))->toBeNull();
    });

    test('denies deletion for regular user', function () {
        $regularUser = $this->createTenantUser(roles: ['user']);
        $config = SystemColumnConfiguration::factory()->create();

        $response = $this->actingAs($regularUser, 'tenant-api')
            ->deleteJson($this->tenantApiUrl("system/column-configurations/{$config->id}"));

        $response->assertForbidden();
        expect(SystemColumnConfiguration::find($config->id))->not->toBeNull();
    });
});
