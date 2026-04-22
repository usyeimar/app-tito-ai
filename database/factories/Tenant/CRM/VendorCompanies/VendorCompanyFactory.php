<?php

declare(strict_types=1);

namespace Database\Factories\Tenant\CRM\VendorCompanies;

use App\Models\Tenant\CRM\VendorCompanies\VendorCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VendorCompany> */
class VendorCompanyFactory extends Factory
{
    protected $model = VendorCompany::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'legal_name' => fake()->company(),
            'website' => fake()->optional()->url(),
            'domain' => fake()->optional()->domainName(),
            'custom_fields' => [],
        ];
    }
}
