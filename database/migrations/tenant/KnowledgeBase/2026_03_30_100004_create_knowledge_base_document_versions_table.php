<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base_document_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('knowledge_base_document_id')->constrained('knowledge_base_documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->longText('content');
            $table->foreignUlid('author_id')->constrained('users');
            $table->string('change_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_document_versions');
    }
};
