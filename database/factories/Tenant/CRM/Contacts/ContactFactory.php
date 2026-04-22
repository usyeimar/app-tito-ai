<?php

declare(strict_types=1);

namespace Database\Factories\Tenant\CRM\Contacts;

use App\Models\Tenant\CRM\Contacts\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'display_name' => fake()->name(),
            'custom_fields' => [],
        ];
    }
}
