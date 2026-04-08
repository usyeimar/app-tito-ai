<?php

namespace Database\Seeders\Central;

use App\Models\Central\Auth\Authentication\CentralUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CentralSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('CENTRAL_SUPER_ADMIN_EMAIL', '');
        $password = (string) env('CENTRAL_SUPER_ADMIN_PASSWORD', '');
        $name = (string) env('CENTRAL_SUPER_ADMIN_NAME', 'Super Admin');

        if ($email === '' || $password === '') {
            return;
        }

        $user = CentralUser::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'global_id' => (string) Str::ulid(),
            ]
        );

        $user->assignRole('super_admin');
    }
}
