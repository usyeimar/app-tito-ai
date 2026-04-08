<?php

namespace Database\Factories\Tenant\System\Configuration;

use App\Models\Tenant\System\Configuration\SystemConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemConfigurationFactory extends Factory
{
    protected $model = SystemConfiguration::class;

    public function definition(): array
    {
        return [
            'key' => 'config_'.$this->faker->unique()->lexify('??????'),
            'data' => null,
            'meta' => null,
        ];
    }

    public function forKey(string $key): static
    {
        return $this->state(fn () => [
            'key' => $key,
        ]);
    }

    public function withData(?array $data): static
    {
        return $this->state(fn () => [
            'data' => $data,
        ]);
    }

    public function withMeta(?array $meta): static
    {
        return $this->state(fn () => [
            'meta' => $meta,
        ]);
    }
}
