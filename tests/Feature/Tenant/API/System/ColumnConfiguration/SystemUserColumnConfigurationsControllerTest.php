<?php

use App\Enums\ModuleType;
use App\Models\Tenant\System\ColumnConfiguration\SystemUserColumnConfiguration;

describe('Index', function () {
    test('returns only current user configurations', function () {
        // Create config for authed user
        SystemUserColumnConfiguration::factory()
            ->forUser($this->user->id)
            ->forModule(ModuleType::LEADS)
            ->create(['data' => ['columns' => ['name']]]);

        // Create config for another user
        $otherUser = $this->createTenantUser();
        SystemUserColumnConfiguration::factory()
            ->forUser($otherUser->id)
            ->forModule(ModuleType::CONTACTS)
            ->create(['data' => ['columns' => ['phone']]]);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->getJson($this->tenantApiUrl('system/user-column-configurations'));

        $response->assertOk();
        expect($response->json('meta.total'))->toBe(1);
        expect($response->json('data.0.module'))->toBe('leads');
    });
});

describe('Store', function () {
    test('creates a user column configuration with 201 status', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('system/user-column-configurations'), [
                'module' => 'leads',
                'data' => ['columns' => ['name', 'email']],
            ]);

        $response->assertCreated()
            ->assertJsonPath('module', 'leads')
            ->assertJsonPath('user_id', $this->user->id)
            ->assertJsonPath('data.columns', ['name', 'email']);
    });

    test('regular user can create their own configuration', function () {
        $regularUser = $this->createTenantUser(roles: ['user']);

        $response = $this->actingAs($regularUser, 'tenant-api')
            ->postJson($this->tenantApiUrl('system/user-column-configurations'), [
                'module' => 'contacts',
                'data' => ['columns' => ['name', 'phone']],
            ]);

        $response->assertCreated()
            ->assertJsonPath('user_id', $regularUser->id);
    });

    test('requires module field', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('system/user-column-configurations'), [
                'data' => ['columns' => ['name']],
            ]);

        $response->assertUnprocessable();
        assertHasValidationError($response, 'module');
    });

    test('requires data field', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('system/user-column-configurations'), [
                'module' => 'leads',
            ]);

        $response->assertUnprocessable();
        assertHasValidationError($response, 'data');
    });

    test('validates module enum', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('system/user-column-configurations'), [
                'module' => 'invalid_module',
                'data' => ['columns' => ['name']],
            ]);

        $response->assertUnprocessable();
    });
});

describe('Show', function () {
    test('returns a single user column configuration', function () {
        $config = SystemUserColumnConfiguration::factory()
            ->forUser($this->user->id)
            ->forModule(ModuleType::LEADS)
            ->create(['data' => ['columns' => ['name']]]);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->getJson($this->tenantApiUrl("system/user-column-configurations/{$config->id}"));

        $response->assertOk()
            ->assertJsonPath('id', $config->id)
            ->assertJsonPath('module', 'leads');
    });

    test('denies access to another user config', function () {
        $otherUser = $this->createTenantUser();
        $config = SystemUserColumnConfiguration::factory()
            ->forUser($otherUser->id)
            ->create();

        $response = $this->actingAs($this->user, 'tenant-api')
            ->getJson($this->tenantApiUrl("system/user-column-configurations/{$config->id}"));

        $response->assertForbidden();
    });
});

describe('Update', function () {
    test('updates a user column configuration', function () {
        $config = SystemUserColumnConfiguration::factory()
            ->forUser($this->user->id)
            ->forModule(ModuleType::LEADS)
            ->create(['data' => ['columns' => ['name']]]);

        $response = $this->actingAs($this->user, 'tenant-api')
            ->patchJson($this->tenantApiUrl("system/user-column-configurations/{$config->id}"), [
                'data' => ['columns' => ['name', 'email', 'phone']],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.columns', ['name', 'email', 'phone']);
    });

    test('module is immutable on update', function () {
        $config = SystemUserColumnConfiguration::factory()
            ->forUser($this->user->id)
            ->forModule(ModuleType::LEADS)
            ->create();

        $response = $this->actingAs($this->user, 'tenant-api')
            ->patchJson($this->tenantApiUrl("system/user-column-configurations/{$config->id}"), [
                'module' => 'contacts',
            ]);

        $response->assertOk();
        $response->assertJsonPath('module', 'leads');
        expect($config->refresh()->module)->toBe('leads');
    });

    test('denies update to another user config', function () {
        $otherUser = $this->createTenantUser();
        $config = SystemUserColumnConfiguration::factory()
            ->forUser($otherUser->id)
            ->create();

        $response = $this->actingAs($this->user, 'tenant-api')
            ->patchJson($this->tenantApiUrl("system/user-column-configurations/{$config->id}"), [
                'data' => ['columns' => ['hacked']],
            ]);

        $response->assertForbidden();
    });
});

describe('Delete', function () {
    test('deletes a user column configuration and returns 204', function () {
        $config = SystemUserColumnConfiguration::factory()
            ->forUser($this->user->id)
            ->create();

        $response = $this->actingAs($this->user, 'tenant-api')
            ->deleteJson($this->tenantApiUrl("system/user-column-configurations/{$config->id}"));

        $response->assertNoContent();
        expect(SystemUserColumnConfiguration::find($config->id))->toBeNull();
    });

    test('denies deletion of another user config', function () {
        $otherUser = $this->createTenantUser();
        $config = SystemUserColumnConfiguration::factory()
            ->forUser($otherUser->id)
            ->create();

        $response = $this->actingAs($this->user, 'tenant-api')
            ->deleteJson($this->tenantApiUrl("system/user-column-configurations/{$config->id}"));

        $response->assertForbidden();
        expect(SystemUserColumnConfiguration::find($config->id))->not->toBeNull();
    });

    test('regular user can delete their own configuration', function () {
        $regularUser = $this->createTenantUser(roles: ['user']);
        $config = SystemUserColumnConfiguration::factory()
            ->forUser($regularUser->id)
            ->create();

        $response = $this->actingAs($regularUser, 'tenant-api')
            ->deleteJson($this->tenantApiUrl("system/user-column-configurations/{$config->id}"));

        $response->assertNoContent();
    });
});
