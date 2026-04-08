<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Tenancy;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user_impersonation_tokens', function (Blueprint $table) {
            $table->string('token', 128)->primary();
            $table->ulid(Tenancy::tenantKeyColumn());
            $table->ulid('user_id');
            $table->boolean('remember');
            $table->string('auth_guard');
            $table->foreignUlid('impersonator_central_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('redirect_url');
            $table->timestamp('created_at');

            $table->foreign(Tenancy::tenantKeyColumn())
                ->references('id')
                ->on('tenants')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_impersonation_tokens');
    }
};
