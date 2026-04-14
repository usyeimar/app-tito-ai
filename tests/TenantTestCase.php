<?php

namespace Tests;

use App\Models\Central\Auth\Role\Role;
use App\Models\Central\Tenancy\Tenant;
use App\Models\Tenant\Auth\Authentication\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

abstract class TenantTestCase extends TestCase
{
    use RefreshDatabase;

    protected ?Tenant $tenant = null;

    protected ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Initialize Tenancy
        $this->tenant = $this->createTestTenant();
        tenancy()->initialize($this->tenant);

        // 2. Clear Permission Cache for the tenant
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 3. Ensure Passport keys exist
        $this->ensurePassportKeys();

        // 4. Create a default Super Admin for the test
        $this->setupDefaultUser();
    }

    protected function createTestTenant(): Tenant
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-'.Str::random(6),
        ]);

        return $tenant;
    }

    protected function setupDefaultUser(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'tenant',
        ]);

        $this->user = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Test Admin',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );

        $this->user->assignRole($role);
    }

    protected function ensurePassportKeys(): void
    {
        if (! file_exists(storage_path('oauth-private.key'))) {
            Artisan::call('passport:keys', ['--force' => true]);
        }
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    protected function tenantApiUrl(string $path): string
    {
        return "/{$this->tenant->slug}/api/{$path}";
    }
}
