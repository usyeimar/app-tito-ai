<?php

declare(strict_types=1);

namespace Database\Factories\Tenant\Commons\Emails;

use App\Models\Tenant\Commons\Emails\Email;
use App\Models\Tenant\CRM\Contacts\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Email> */
class EmailFactory extends Factory
{
    protected $model = Email::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'emailable_type' => Contact::class,
            'emailable_id' => Contact::factory(),
            'email' => fake()->unique()->safeEmail(),
            'label' => 'work',
            'is_primary' => true,
        ];
    }
}
