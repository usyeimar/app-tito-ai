<?php

namespace Database\Seeders\Central;

use App\Models\Central\Auth\Authentication\CentralUser;
use App\Models\Central\Auth\SocialLogin\SocialAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CentralAuthDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $users = [
            [
                'email' => 'admin@tito.ai',
                'name' => 'Central Admin',
                'role' => 'admin',
                'verified' => true,
                'two_factor_enabled' => false,
            ],
            [
                'email' => 'support@tito.ai',
                'name' => 'Support Agent',
                'role' => 'support',
                'verified' => true,
                'two_factor_enabled' => false,
            ],
            [
                'email' => 'security@tito.ai',
                'name' => 'Security Admin',
                'role' => 'admin',
                'verified' => true,
                'two_factor_enabled' => true,
            ],
            [
                'email' => 'unverified@tito.ai',
                'name' => 'Pending Verification',
                'role' => 'support',
                'verified' => false,
                'two_factor_enabled' => false,
            ],
        ];

        foreach ($users as $seedUser) {
            $user = CentralUser::query()->firstOrNew(['email' => $seedUser['email']]);

            if (! $user->exists) {
                $user->password = Hash::make('ChangeMe123!');
                $user->global_id = (string) Str::ulid();
            }

            $user->name = $seedUser['name'];
            $user->email_verified_at = $seedUser['verified'] ? now() : null;
            $user->email_verification_sent_at = $seedUser['verified'] ? null : now();
            $user->two_factor_enabled = (bool) $seedUser['two_factor_enabled'];
            $user->two_factor_secret = $seedUser['two_factor_enabled'] ? 'seeded-two-factor-secret' : null;
            $user->two_factor_recovery_codes = $seedUser['two_factor_enabled']
                ? ['A1B2C3D4', 'E5F6G7H8', 'I9J0K1L2']
                : null;
            $user->two_factor_confirmed_at = $seedUser['two_factor_enabled'] ? now() : null;
            $user->save();

            $user->syncRoles([$seedUser['role']]);
        }

        $socialAccounts = [
            [
                'email' => 'admin@tito.ai',
                'provider' => 'google',
                'provider_user_id' => 'seed-google-admin',
            ],
            [
                'email' => 'support@tito.ai',
                'provider' => 'microsoft',
                'provider_user_id' => 'seed-microsoft-support',
            ],
            [
                'email' => 'security@tito.ai',
                'provider' => 'google',
                'provider_user_id' => 'seed-google-security',
            ],
        ];

        foreach ($socialAccounts as $account) {
            $user = CentralUser::query()->where('email', $account['email'])->first();

            if (! $user) {
                continue;
            }

            SocialAccount::query()->updateOrCreate(
                [
                    'provider' => $account['provider'],
                    'provider_user_id' => $account['provider_user_id'],
                ],
                [
                    'user_id' => $user->getKey(),
                    'email' => $user->email,
                ]
            );
        }
    }
}
