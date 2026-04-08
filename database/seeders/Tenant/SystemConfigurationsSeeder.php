<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\System\Configuration\SystemConfiguration;
use Illuminate\Database\Seeder;

class SystemConfigurationsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ((array) config('system-configurations.defaults', []) as $definition) {
            $key = (string) ($definition['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $configuration = SystemConfiguration::query()->firstOrCreate(
                ['key' => $key],
                [
                    'data' => $definition['data'] ?? null,
                    'meta' => $definition['meta'] ?? null,
                ],
            );

            if ($configuration->meta === null && array_key_exists('meta', $definition)) {
                $configuration->meta = $definition['meta'];
                $configuration->save();
            }
        }
    }
}
