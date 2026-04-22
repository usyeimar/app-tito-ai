<?php

declare(strict_types=1);

namespace Database\Factories\Tenant\Agent;

use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\AgentTool;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgentTool> */
class AgentToolFactory extends Factory
{
    protected $model = AgentTool::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'name' => fake()->word(),
            'description' => fake()->sentence(),
            'parameters' => [],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(fn () => ['agent_id' => $agent->id]);
    }
}
