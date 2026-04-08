<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->unique()->constrained('agents')->cascadeOnDelete();

            $table->jsonb('brain_config')->default('{}');
            $table->jsonb('runtime_config')->default('{}');
            $table->jsonb('architecture_config')->default('{}');
            $table->jsonb('capabilities_config')->default('{}');
            $table->jsonb('observability_config')->default('{}');

            $table->timestamps();
        });

        // Add GIN indexes
        DB::statement('CREATE INDEX idx_agent_settings_brain_gin ON agent_settings USING GIN (brain_config)');
        DB::statement("CREATE INDEX idx_agent_settings_brain_provider ON agent_settings ((brain_config->>'provider'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_settings');
    }
};
