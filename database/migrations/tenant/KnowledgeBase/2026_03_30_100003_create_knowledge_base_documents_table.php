<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('knowledge_base_category_id')->constrained('knowledge_base_categories')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('content_format')->default('markdown');
            $table->string('status')->default('draft');
            $table->foreignUlid('author_id')->constrained('users');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_documents');
    }
};
