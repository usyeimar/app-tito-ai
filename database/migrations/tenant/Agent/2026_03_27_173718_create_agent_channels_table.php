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
        Schema::create('agent_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('channel'); // web, sip, whatsapp
            $table->boolean('enabled')->default(true);
            $table->jsonb('config')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_channels');
    }
};
