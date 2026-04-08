<?php

namespace Database\Factories\Central\Auth\Authentication;

use App\Models\Central\Auth\Authentication\CentralUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<CentralUser>
 */
class CentralUserFactory extends Factory
{
    protected $model = CentralUser::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'global_id' => (string) Str::ulid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
