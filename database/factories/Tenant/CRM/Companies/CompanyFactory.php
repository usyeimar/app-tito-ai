<?php

declare(strict_types=1);

namespace Database\Factories\Tenant\CRM\Companies;

use App\Models\Tenant\CRM\Companies\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'normalized_name' => null,
            'legal_name' => fake()->company(),
            'external_ref' => fake()->optional()->uuid(),
            'is_active' => true,
            'website' => fake()->optional()->url(),
            'domain' => fake()->optional()->domainName(),
            'custom_fields' => [],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
