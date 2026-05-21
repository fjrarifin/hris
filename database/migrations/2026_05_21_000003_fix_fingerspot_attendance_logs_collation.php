<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fingerspot_attendance_logs')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement(
            'ALTER TABLE fingerspot_attendance_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        //
    }
};
