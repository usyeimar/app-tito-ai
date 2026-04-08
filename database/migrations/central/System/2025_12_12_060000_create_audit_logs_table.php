<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->nullable()->index();
            $table->foreignUlid('actor_central_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->ulid('tenant_user_id')->nullable()->index();
            $table->string('route')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->unsignedSmallInteger('status');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
