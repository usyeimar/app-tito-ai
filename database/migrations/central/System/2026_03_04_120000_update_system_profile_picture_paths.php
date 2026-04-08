<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_profile_pictures')) {
            return;
        }

        DB::statement(<<<'SQL'
            UPDATE system_profile_pictures
            SET path = REPLACE(path, 'central/profile-images/', 'profile-images/users/')
            WHERE path LIKE 'central/profile-images/%'
        SQL);
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_profile_pictures')) {
            return;
        }

        DB::statement(<<<'SQL'
            UPDATE system_profile_pictures
            SET path = REPLACE(path, 'profile-images/users/', 'central/profile-images/')
            WHERE path LIKE 'profile-images/users/%'
        SQL);
    }
};
