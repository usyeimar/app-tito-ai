<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_profile_pictures', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('global_id')->unique();
            $table->string('user_global_id')->unique();
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_profile_pictures');
    }
};
