<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerspot_attendance_logs', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('pin', 50)->index();
            $table->dateTime('scan_date')->index();
            $table->string('verify', 20)->nullable();
            $table->string('status_scan', 20)->nullable();
            $table->string('trans_id', 100)->nullable()->index();
            $table->string('cloud_id', 100)->nullable()->index();
            $table->string('source', 30)->default('pull')->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['pin', 'scan_date', 'status_scan'], 'fingerspot_attendance_unique_scan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerspot_attendance_logs');
    }
};
