<?php

declare(strict_types=1);

namespace Database\Factories\Tenant\Agent;

use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\Trunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trunk>
 */
class TrunkFactory extends Factory
{
    protected $model = Trunk::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => null,
            'workspace_slug' => 'default',
            'name' => $this->faker->words(3, true).' Trunk',
            'mode' => Trunk::MODE_INBOUND,
            'max_concurrent_calls' => $this->faker->numberBetween(5, 50),
            'codecs' => ['ulaw', 'alaw'],
            'status' => Trunk::STATUS_ACTIVE,
            'inbound_auth' => [
                'auth_type' => 'ip',
                'allowed_ips' => ['0.0.0.0/0'],
            ],
            'routes' => [
                [
                    'pattern' => '*',
                    'agent_id' => null, // Set via withAgent() or manually
                    'priority' => 0,
                    'enabled' => true,
                ],
            ],
            'sip_host' => $this->faker->domainName(),
            'sip_port' => 5060,
            'register_config' => null,
            'outbound' => null,
        ];
    }

    /**
     * Indicate that the trunk is for inbound calls.
     */
    public function inbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => Trunk::MODE_INBOUND,
        ]);
    }

    /**
     * Indicate that the trunk is for SIP registration.
     */
    public function register(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => Trunk::MODE_REGISTER,
            'register_config' => [
                'server' => 'sip.example.com',
                'port' => 5060,
                'username' => $this->faker->userName(),
                'password' => $this->faker->password(8, 16),
                'register_interval' => 60,
            ],
        ]);
    }

    /**
     * Indicate that the trunk is for outbound calls.
     */
    public function outbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => Trunk::MODE_OUTBOUND,
            'outbound' => [
                'trunk_name' => $this->faker->company(),
                'server' => 'sip.example.com',
                'port' => 5060,
                'username' => $this->faker->userName(),
                'password' => $this->faker->password(8, 16),
                'caller_id' => $this->faker->phoneNumber(),
            ],
        ]);
    }

    /**
     * Associate with an agent.
     */
    public function withAgent(Agent $agent): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_id' => $agent->id,
            'routes' => [
                [
                    'pattern' => '*',
                    'agent_id' => (string) $agent->id,
                    'priority' => 0,
                    'enabled' => true,
                ],
            ],
        ]);
    }

    /**
     * Indicate that the trunk is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Trunk::STATUS_ACTIVE,
        ]);
    }

    /**
     * Indicate that the trunk is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Trunk::STATUS_INACTIVE,
        ]);
    }
}
