<?php

declare(strict_types=1);

namespace Database\Factories\Tenant\Commons\Addresses;

use App\Models\Tenant\Commons\Addresses\Address;
use App\Models\Tenant\CRM\Companies\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Address> */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'addressable_type' => Company::class,
            'addressable_id' => Company::factory(),
            'address_line' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state_region' => fake()->state(),
            'country_code' => fake()->countryCode(),
            'postal_code' => fake()->postcode(),
            'label' => 'main',
            'is_primary' => true,
        ];
    }
}
