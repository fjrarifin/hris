<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fingerspot_attendance_logs', function (Blueprint $table) {
            $table->dropUnique('fingerspot_attendance_unique_scan');
            $table->unique(
                ['cloud_id', 'pin', 'scan_date', 'status_scan'],
                'fingerspot_attendance_unique_cloud_scan'
            );
        });
    }

    public function down(): void
    {
        Schema::table('fingerspot_attendance_logs', function (Blueprint $table) {
            $table->dropUnique('fingerspot_attendance_unique_cloud_scan');
            $table->unique(
                ['pin', 'scan_date', 'status_scan'],
                'fingerspot_attendance_unique_scan'
            );
        });
    }
};
