<?php

namespace Database\Seeders\Tenant;

use App\Jobs\Central\Tenancy\SeedDevelopmentTenantData;
use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionsSeeder::class,
            SystemConfigurationsSeeder::class,
            PassportClientsSeeder::class,
        ]);

        if (! $this->shouldSeedDevelopmentData()) {
            return;
        }

        if ($this->shouldSeedDevelopmentDataAfterResponse()) {
            $tenantId = $this->currentTenantId();

            if ($tenantId !== null) {
                // Keep the HTTP response fast while still backfilling demo data.
                SeedDevelopmentTenantData::dispatchAfterResponse($tenantId);

                return;
            }
        }

        //        $this->call([DevelopmentTenantDatabaseSeeder::class]);
    }

    protected function shouldSeedDevelopmentData(): bool
    {
        return (bool) config('app.development_seeders');
    }

    protected function shouldSeedDevelopmentDataAfterResponse(): bool
    {
        return ! app()->environment('local') && ! app()->runningInConsole();
    }

    protected function currentTenantId(): ?string
    {
        $tenant = tenant();

        if (! $tenant) {
            return null;
        }

        return (string) $tenant->getTenantKey();
    }
}
