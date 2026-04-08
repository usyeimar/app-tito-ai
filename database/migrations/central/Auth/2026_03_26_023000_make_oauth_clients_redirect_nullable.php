<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make legacy Passport columns nullable so Passport v13 inserts succeed.
     *
     * Passport v13 replaced redirect/personal_access_client/password_client
     * with redirect_uris/grant_types, but the old columns still exist.
     */
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->text('redirect')->nullable()->change();
            $table->boolean('personal_access_client')->nullable()->change();
            $table->boolean('password_client')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->text('redirect')->nullable(false)->change();
            $table->boolean('personal_access_client')->nullable(false)->change();
            $table->boolean('password_client')->nullable(false)->change();
        });
    }
};
