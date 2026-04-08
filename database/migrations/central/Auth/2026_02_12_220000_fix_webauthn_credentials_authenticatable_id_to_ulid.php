<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('webauthn_credentials') || ! Schema::hasColumn('webauthn_credentials', 'authenticatable_id')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS webauthn_user_index');
        DB::statement('ALTER TABLE "webauthn_credentials" ALTER COLUMN "authenticatable_id" TYPE char(26) USING "authenticatable_id"::text');
        DB::statement('CREATE INDEX webauthn_user_index ON "webauthn_credentials" ("authenticatable_type", "authenticatable_id")');
    }

    public function down(): void
    {
        // Intentionally left blank. Reverting this can truncate non-numeric ULID values.
    }
};
