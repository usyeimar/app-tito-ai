<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('language', 10)->default('es-CO');
            $table->jsonb('tags')->default('{}');
            $table->string('timezone', 50)->default('UTC');
            $table->string('currency', 3)->default('COP');
            $table->string('number_format', 20)->default('es_CO');
            $table->foreignUuid('knowledge_base_id')->nullable()->constrained('knowledge_bases')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
