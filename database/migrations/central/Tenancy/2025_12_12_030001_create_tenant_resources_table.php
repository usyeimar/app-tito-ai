<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('resource_global_id');
            $table->string('tenant_resources_type');

            $table->unique(['tenant_id', 'resource_global_id', 'tenant_resources_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_resources');
    }
};
