<?php

namespace Tests;

use App\Models\Central\Auth\Role\Role;
use App\Models\Central\Tenancy\Tenant;
use App\Models\Tenant\Auth\Authentication\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

abstract class TenantTestCase extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as protected beginDatabaseTransactionTrait;
    }

    protected static ?string $sharedTenantId = null;

    protected ?Tenant $tenant = null;

    protected ?User $user = null;

    /**
     * Transactions are started manually after tenancy is initialized.
     */
    public function beginDatabaseTransaction(): void
    {
        // Intentionally handled in setUp().
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->usesTypesenseIntegration() && ! $this->typesenseIntegrationEnabled()) {
            $this->markTestSkipped('Set RUN_TYPESENSE_INTEGRATION_TESTS=1 to run live Typesense integration tests.');
        }

        config(['app.development_seeders' => false]);
        config(['cache.default' => 'file']);
        config(['tenancy.cache.stores' => ['file']]);
        $this->configureSearchTesting();

        $this->ensurePassportKeys();
        $this->bootstrapSharedTenantDatabase();

        $this->tenant = Tenant::query()->findOrFail(static::$sharedTenantId);

        tenancy()->initialize($this->tenant);

        if ($this->shouldStartTenantTransactions()) {
            $this->beginDatabaseTransactionTrait();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdminRole = Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'tenant',
        ]);

        $this->user = User::query()->create([
            'name' => 'Test Super Admin',
            'email' => $this->superAdminEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->user->assignRole($superAdminRole);
    }

    protected function configureSearchTesting(): void
    {
        if ($this->usesTypesenseIntegration()) {
            config([
                'scout.driver' => 'typesense',
                'scout.queue' => false,
                'scout.after_commit' => false,
            ]);

            Http::allowStrayRequests();

            return;
        }

        config(['scout.driver' => null]);

        // Prevent Typesense HTTP calls during tenant lifecycle
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 200)]);
    }

    protected function shouldStartTenantTransactions(): bool
    {
        return true;
    }

    protected function usesTypesenseIntegration(): bool
    {
        return false;
    }

    protected function typesenseIntegrationEnabled(): bool
    {
        $value = $_SERVER['RUN_TYPESENSE_INTEGRATION_TESTS']
            ?? $_ENV['RUN_TYPESENSE_INTEGRATION_TESTS']
            ?? getenv('RUN_TYPESENSE_INTEGRATION_TESTS')
            ?? false;

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    protected function superAdminEmail(): string
    {
        return 'admin@test-tenant.localhost';
    }

    protected function connectionsToTransact(): array
    {
        return [
            config('tenancy.database.central_connection', 'central'),
            'tenant',
        ];
    }

    protected function ensurePassportKeys(): void
    {
        $privateKey = storage_path('oauth-private.key');
        $publicKey = storage_path('oauth-public.key');

        if (! file_exists($privateKey) || ! file_exists($publicKey)) {
            Artisan::call('passport:keys', ['--force' => true]);
        }
    }

    protected function bootstrapSharedTenantDatabase(): void
    {
        if (static::$sharedTenantId !== null && Tenant::query()->whereKey(static::$sharedTenantId)->exists()) {
            return;
        }

        $this->artisan('migrate:fresh', [
            '--database' => config('tenancy.database.central_connection', 'central'),
            '--force' => true,
        ]);

        $suffix = trim((string) env('TEST_TOKEN', ''));
        $slugSuffix = $suffix !== '' ? '-'.$suffix : '';

        $tenant = Tenant::query()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant'.$slugSuffix.'-'.Str::lower(Str::random(6)),
        ]);

        static::$sharedTenantId = (string) $tenant->getKey();
    }

    protected function tearDown(): void
    {
        $this->tenant = null;
        $this->user = null;

        parent::tearDown();
    }

    protected function createTenantUser(array $attrs = [], array $roles = []): User
    {
        $user = User::query()->create(array_merge([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'is_active' => true,
        ], $attrs));

        foreach ($roles as $role) {
            $roleName = is_string($role) ? $role : $role->name;
            $roleModel = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'tenant',
            ]);
            $user->assignRole($roleModel);
        }

        return $user;
    }

    protected function tenantApiUrl(string $path): string
    {
        $slug = $this->tenant->slug;

        return "/{$slug}/api/{$path}";
    }
}
