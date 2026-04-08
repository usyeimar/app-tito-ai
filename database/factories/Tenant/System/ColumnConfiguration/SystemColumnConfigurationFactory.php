<?php

namespace Database\Factories\Tenant\System\ColumnConfiguration;

use App\Enums\ModuleType;
use App\Models\Tenant\System\ColumnConfiguration\SystemColumnConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemColumnConfigurationFactory extends Factory
{
    protected $model = SystemColumnConfiguration::class;

    public function definition(): array
    {
        return [
            'module' => $this->faker->randomElement(ModuleType::cases()),
            'data' => ['columns' => ['name', 'email', 'status']],
        ];
    }

    public function forModule(ModuleType|string $module): static
    {
        return $this->state(fn () => [
            'module' => $module instanceof ModuleType ? $module->value : $module,
        ]);
    }
}
