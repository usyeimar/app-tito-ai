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
        Schema::create('trunks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('workspace_slug')->index();
            $table->string('name');
            $table->string('mode', 20)->default('inbound'); // inbound, register, outbound
            $table->unsignedInteger('max_concurrent_calls')->default(10);
            $table->jsonb('codecs')->default('["ulaw", "alaw"]');
            $table->string('status', 20)->default('active'); // active, inactive, suspended
            $table->jsonb('inbound_auth')->nullable(); // {auth_type: ip|userpass, allowed_ips: [], username?, password?}
            $table->jsonb('routes')->nullable(); // [{pattern, agent_id, priority, enabled}]
            $table->string('sip_host')->nullable();
            $table->unsignedInteger('sip_port')->default(5060);
            $table->jsonb('register_config')->nullable(); // {server, port, username, password, register_interval}
            $table->jsonb('outbound')->nullable(); // {trunk_name, server, port, username, password, caller_id}
            $table->timestamps();

            $table->index(['workspace_slug', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trunks');
    }
};
