<?php

declare(strict_types=1);

namespace Database\Factories\Tenant\CRM\Leads;

use App\Models\Tenant\CRM\Leads\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lead> */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'display_name' => fake()->name(),
            'lead_number' => 'LD-'.fake()->unique()->numerify('####'),
            'company_name' => fake()->optional()->company(),
            'custom_fields' => [],
        ];
    }

    public function converted(): static
    {
        return $this->state(fn () => ['converted_at' => now()]);
    }
}
