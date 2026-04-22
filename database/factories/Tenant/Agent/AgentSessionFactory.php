<?php

declare(strict_types=1);

namespace Database\Factories\Tenant\Agent;

use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\AgentSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgentSession> */
class AgentSessionFactory extends Factory
{
    protected $model = AgentSession::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'channel' => fake()->randomElement(['web-widget', 'sip', 'whatsapp']),
            'external_session_id' => 'sess_'.fake()->uuid(),
            'status' => 'completed',
            'metadata' => [],
            'started_at' => now()->subMinutes(10),
            'ended_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'ended_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => 'failed']);
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(fn () => ['agent_id' => $agent->id]);
    }
}
