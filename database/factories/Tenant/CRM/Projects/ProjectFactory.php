<?php

declare(strict_types=1);

namespace Database\Factories\Tenant\CRM\Projects;

use App\Models\Tenant\CRM\Projects\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'project_number' => 'PJ-'.fake()->unique()->numerify('####'),
            'is_active' => true,
            'start_date' => fake()->optional()->date(),
            'end_date' => fake()->optional()->date(),
            'custom_fields' => [],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
