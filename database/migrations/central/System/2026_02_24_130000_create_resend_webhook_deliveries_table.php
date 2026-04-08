<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resend_webhook_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('dedupe_key', 191)->unique();
            $table->string('svix_id', 191)->nullable()->index();
            $table->string('event_type', 64)->nullable()->index();
            $table->string('tenant_id_tag', 64)->nullable()->index();
            $table->string('tenant_slug_tag', 191)->nullable()->index();
            $table->string('status', 32)->default('pending')->index();
            $table->string('reason_code', 64)->nullable()->index();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestampTz('received_at')->nullable()->index();
            $table->timestampTz('processed_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->jsonb('headers')->nullable();
            $table->jsonb('payload');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resend_webhook_deliveries');
    }
};
