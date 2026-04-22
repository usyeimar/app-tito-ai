<?php

declare(strict_types=1);

namespace Database\Factories\Tenant\Commons\Phones;

use App\Models\Tenant\Commons\Phones\Phone;
use App\Models\Tenant\CRM\Contacts\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Phone> */
class PhoneFactory extends Factory
{
    protected $model = Phone::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'phoneable_type' => Contact::class,
            'phoneable_id' => Contact::factory(),
            'phone' => fake()->phoneNumber(),
            'country_code' => 'US',
            'label' => 'mobile',
            'is_primary' => true,
        ];
    }
}
