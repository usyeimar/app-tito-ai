<?php

namespace Database\Factories\Tenant\System\ColumnConfiguration;

use App\Enums\ModuleType;
use App\Models\Tenant\System\ColumnConfiguration\SystemUserColumnConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemUserColumnConfigurationFactory extends Factory
{
    protected $model = SystemUserColumnConfiguration::class;

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

    public function forUser(string $userId): static
    {
        return $this->state(fn () => [
            'user_id' => $userId,
        ]);
    }
}
