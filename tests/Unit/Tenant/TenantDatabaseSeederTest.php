<?php

use App\Jobs\Central\Tenancy\SeedDevelopmentTenantData;
use Database\Seeders\Tenant\DevelopmentTenantDatabaseSeeder;
use Database\Seeders\Tenant\PassportClientsSeeder;
use Database\Seeders\Tenant\PermissionsSeeder;
use Database\Seeders\Tenant\SystemConfigurationsSeeder;
use Database\Seeders\Tenant\TenantDatabaseSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

uses(TestCase::class);

it('only runs required tenant seeders when development seeders are disabled', function (): void {
    Bus::fake();

    config()->set('app.development_seeders', false);

    $seeder = new FakeTenantDatabaseSeeder;
    $seeder->run();

    expect($seeder->calls)->toBe([
        [PermissionsSeeder::class, SystemConfigurationsSeeder::class, PassportClientsSeeder::class],
    ]);

    Bus::assertNothingDispatched();
});

it('dispatches development tenant seeders after the response for http provisioning', function (): void {
    Bus::fake();

    config()->set('app.development_seeders', true);

    $seeder = new FakeTenantDatabaseSeeder;
    $seeder->seedAfterResponse = true;
    $seeder->tenantId = 'tenant_123';
    $seeder->run();

    expect($seeder->calls)->toBe([
        [PermissionsSeeder::class, SystemConfigurationsSeeder::class, PassportClientsSeeder::class],
    ]);

    Bus::assertDispatchedAfterResponse(
        SeedDevelopmentTenantData::class,
        fn (SeedDevelopmentTenantData $job): bool => $job->tenantId === 'tenant_123',
    );
});

it('falls back to inline development seeding when tenant context is unavailable', function (): void {
    Bus::fake();

    config()->set('app.development_seeders', true);

    $seeder = new FakeTenantDatabaseSeeder;
    $seeder->seedAfterResponse = true;
    $seeder->tenantId = null;
    $seeder->run();

    expect($seeder->calls)->toBe([
        [PermissionsSeeder::class, SystemConfigurationsSeeder::class, PassportClientsSeeder::class],
    ]);

    Bus::assertNotDispatched(SeedDevelopmentTenantData::class);
});

it('seeds development tenant data through the tenancy seeder command', function (): void {
    Artisan::spy();

    $job = new SeedDevelopmentTenantData('tenant_123');
    $job->handle();

    Artisan::shouldHaveReceived('call')
        ->once()
        ->with('tenants:seed', [
            '--class' => DevelopmentTenantDatabaseSeeder::class,
            '--force' => true,
            '--tenants' => ['tenant_123'],
        ]);
});

class FakeTenantDatabaseSeeder extends TenantDatabaseSeeder
{
    /**
     * @var list<list<string>>
     */
    public array $calls = [];

    public bool $seedAfterResponse = false;

    public ?string $tenantId = null;

    public function call($class, $silent = false, array $parameters = [])
    {
        $this->calls[] = array_values(Arr::wrap($class));

        return $this;
    }

    protected function shouldSeedDevelopmentDataAfterResponse(): bool
    {
        return $this->seedAfterResponse;
    }

    protected function currentTenantId(): ?string
    {
        return $this->tenantId;
    }
}
