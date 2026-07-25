<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerspot_user_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('pin', 50)->unique();
            $table->string('name', 150)->nullable();
            $table->string('cloud_id', 100)->nullable();
            $table->string('privilege', 20)->default('0');
            $table->string('password', 100)->nullable();
            $table->string('card', 100)->nullable();
            $table->longText('template')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('last_pulled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerspot_user_templates');
    }
};
