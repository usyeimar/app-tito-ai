<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_event_relations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('activity_event_id')->constrained('activity_events')->cascadeOnDelete();
            $table->string('related_type', 64);
            $table->string('related_id', 64);
            $table->string('related_label')->nullable();
            $table->string('relation', 64)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['related_type', 'related_id', 'occurred_at'], 'activity_event_relations_related_timeline_idx');
            $table->index('activity_event_id', 'activity_event_relations_event_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_event_relations');
    }
};
