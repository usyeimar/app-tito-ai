<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_column_configurations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('module');
            $table->jsonb('data');
            $table->timestampsTz();
        });

        Schema::create('system_user_column_configurations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('module');
            $table->jsonb('data');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_user_column_configurations');
        Schema::dropIfExists('system_column_configurations');
    }
};
