<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerspot_webhook_logs', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('type', 50)->nullable()->index();
            $table->string('cloud_id', 100)->nullable()->index();
            $table->string('pin', 50)->nullable()->index();
            $table->dateTime('scan')->nullable()->index();
            $table->string('verify', 20)->nullable();
            $table->string('status_scan', 20)->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('received_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerspot_webhook_logs');
    }
};
