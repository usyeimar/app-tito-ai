<?php

declare(strict_types=1);

namespace Database\Factories\Tenant\Agent;

use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\AgentSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'slug' => $this->faker->slug(),
            'description' => $this->faker->paragraph(),
            'language' => 'es-CO',
            'tags' => ['test'],
            'timezone' => 'UTC',
            'currency' => 'COP',
            'number_format' => 'es_CO',
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Agent $agent) {
            if (! $agent->settings()->exists()) {
                AgentSetting::create([
                    'agent_id' => $agent->id,
                    'brain_config' => [],
                    'runtime_config' => [],
                    'architecture_config' => [],
                    'capabilities_config' => [],
                    'observability_config' => [],
                ]);
            }
        });
    }
}
