<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('token_hash', 64)->unique();
            $table->foreignUlid('invited_by_central_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX tenant_invitations_pending_tenant_email_unique ON tenant_invitations (tenant_id, lower(email)) WHERE status = 'pending'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tenant_invitations_pending_tenant_email_unique');
        Schema::dropIfExists('tenant_invitations');
    }
};
