<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('knowledge_base_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->char('parent_id', 26)->nullable();
            $table->string('name');
            $table->string('slug');
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        Schema::table('knowledge_base_categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('knowledge_base_categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_categories');
    }
};
