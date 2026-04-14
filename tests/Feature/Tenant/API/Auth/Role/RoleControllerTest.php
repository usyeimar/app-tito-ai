<?php

use App\Models\Central\Auth\Role\Role;
use App\Models\Tenant\Auth\Authentication\User;

describe('Roles API', function () {
    describe('Index', function () {
        it('requires authentication', function () {
            $response = $this->getJson($this->tenantApiUrl('roles'));
            $response->assertUnauthorized();
        });

        it('lists roles with pagination', function () {
            for ($i = 0; $i < 5; $i++) {
                Role::create(['name' => "test_role_{$i}", 'guard_name' => 'tenant']);
            }

            $response = $this->actingAs($this->user, 'tenant-api')
                ->getJson($this->tenantApiUrl('roles'));

            if ($response->status() !== 200) {
                dump($response->json());
            }
            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        'roles',
                        'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                        'links',
                    ],
                ]);
        });

        it('denies access for non-super_admin user', function () {
            $regularUser = User::factory()->create();

            $response = $this->actingAs($regularUser, 'tenant-api')
                ->getJson($this->tenantApiUrl('roles'));

            $response->assertForbidden();
        });
    });

    describe('Store', function () {
        it('creates a role', function () {
            $response = $this->actingAs($this->user, 'tenant-api')
                ->postJson($this->tenantApiUrl('roles'), [
                    'name' => 'new_role_'.uniqid(),
                    'guard_name' => 'tenant',
                ]);

            $response->assertCreated();
        });

        it('requires super_admin to create role', function () {
            $regularUser = User::factory()->create();

            $response = $this->actingAs($regularUser, 'tenant-api')
                ->postJson($this->tenantApiUrl('roles'), [
                    'name' => 'unauthorized_role',
                    'guard_name' => 'tenant',
                ]);

            $response->assertForbidden();
        });

        it('requires verified email to create role', function () {
            $unverifiedUser = User::factory()->create(['email_verified_at' => null]);

            $response = $this->actingAs($unverifiedUser, 'tenant-api')
                ->postJson($this->tenantApiUrl('roles'), [
                    'name' => 'unauthorized_role',
                    'guard_name' => 'tenant',
                ]);

            $response->assertForbidden();
        });
    });

    describe('Show', function () {
        it('shows a role', function () {
            $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'tenant']);

            $response = $this->actingAs($this->user, 'tenant-api')
                ->getJson($this->tenantApiUrl("roles/{$role->id}"));

            $response->assertOk()
                ->assertJsonPath('data.name', $role->name);
        });

        it('returns 404 for non-existent role', function () {
            $response = $this->actingAs($this->user, 'tenant-api')
                ->getJson($this->tenantApiUrl('roles/01HX99999999999999999999999'));

            $response->assertNotFound();
        });
    });

    describe('Update', function () {
        it('updates a role', function () {
            $role = Role::create(['name' => 'original_role_'.uniqid(), 'guard_name' => 'tenant']);

            $response = $this->actingAs($this->user, 'tenant-api')
                ->patchJson($this->tenantApiUrl("roles/{$role->id}"), [
                    'name' => 'updated_role_'.uniqid(),
                ]);

            $response->assertOk();
        });

        it('denies update for non-super_admin', function () {
            $regularUser = User::factory()->create();
            $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'tenant']);

            $response = $this->actingAs($regularUser, 'tenant-api')
                ->patchJson($this->tenantApiUrl("roles/{$role->id}"), [
                    'name' => 'hacked_role',
                ]);

            $response->assertForbidden();
        });
    });

    describe('Destroy', function () {
        it('deletes a role', function () {
            $role = Role::create(['name' => 'deletable_role_'.uniqid(), 'guard_name' => 'tenant']);

            $response = $this->actingAs($this->user, 'tenant-api')
                ->deleteJson($this->tenantApiUrl("roles/{$role->id}"));

            $response->assertOk();
            expect(Role::find($role->id))->toBeNull();
        });

        it('denies delete for non-super_admin', function () {
            $regularUser = User::factory()->create();
            $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'tenant']);

            $response = $this->actingAs($regularUser, 'tenant-api')
                ->deleteJson($this->tenantApiUrl("roles/{$role->id}"));

            $response->assertForbidden();
        });
    });
});
