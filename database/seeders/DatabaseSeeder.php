<?php

namespace Database\Seeders;

use Database\Seeders\Central\CentralAuthDemoSeeder;
use Database\Seeders\Central\CentralPermissionsSeeder;
use Database\Seeders\Central\CentralSuperAdminSeeder;
use Database\Seeders\Central\CentralUsersSeeder;
use Database\Seeders\Central\PassportClientsSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CentralPermissionsSeeder::class,
            CentralSuperAdminSeeder::class,
            CentralAuthDemoSeeder::class,
            CentralUsersSeeder::class,
            PassportClientsSeeder::class,
        ]);
    }
}
