<?php

declare(strict_types=1);

namespace Database\Factories\Tenant\Agent;

use App\Enums\DeploymentChannel;
use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\AgentDeployment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgentDeployment> */
class AgentDeploymentFactory extends Factory
{
    protected $model = AgentDeployment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'channel' => fake()->randomElement(DeploymentChannel::cases())->value,
            'enabled' => true,
            'config' => [],
            'version' => '1.0.0',
            'status' => 'active',
            'deployed_at' => now(),
            'metadata' => [],
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false, 'deployed_at' => null]);
    }

    public function webWidget(): static
    {
        return $this->state(fn () => [
            'channel' => DeploymentChannel::WebWidget->value,
            'config' => ['theme' => 'light', 'position' => 'bottom-right'],
        ]);
    }

    public function sip(): static
    {
        return $this->state(fn () => [
            'channel' => DeploymentChannel::Sip->value,
            'config' => ['extension' => '1001'],
        ]);
    }

    public function whatsapp(): static
    {
        return $this->state(fn () => [
            'channel' => DeploymentChannel::Whatsapp->value,
            'config' => ['phone_number_id' => fake()->numerify('##########')],
        ]);
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(fn () => ['agent_id' => $agent->id]);
    }
}
