<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('channel')->nullable();
            $table->string('external_session_id')->nullable()->unique();
            $table->string('status')->default('active');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_session_transcripts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('agent_session_id')->constrained('agent_sessions')->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->timestamp('timestamp')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_session_audios', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('agent_session_id')->constrained('agent_sessions')->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->bigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_session_audios');
        Schema::dropIfExists('agent_session_transcripts');
        Schema::dropIfExists('agent_sessions');
    }
};
