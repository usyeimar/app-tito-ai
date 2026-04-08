<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('subject_type', 64);
            $table->string('subject_id', 64);
            $table->string('subject_label')->nullable();
            $table->string('event_type', 120);

            $table->string('actor_type', 64)->nullable();
            $table->string('actor_id', 64)->nullable();
            $table->string('actor_label')->nullable();

            $table->string('origin', 32);
            $table->string('request_id', 128)->nullable();

            $table->string('workflow_actor_type', 64)->nullable();
            $table->string('workflow_actor_id', 64)->nullable();
            $table->string('workflow_actor_label')->nullable();
            $table->ulid('workflow_run_id')->nullable();

            $table->jsonb('changes')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'activity_events_subject_timeline_idx');
            $table->index(['event_type', 'occurred_at'], 'activity_events_event_type_idx');
            $table->index(['actor_type', 'actor_id', 'occurred_at'], 'activity_events_actor_idx');
            $table->index(['origin', 'occurred_at'], 'activity_events_origin_idx');
            $table->index('request_id', 'activity_events_request_id_idx');
            $table->index('workflow_run_id', 'activity_events_workflow_run_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
    }
};
