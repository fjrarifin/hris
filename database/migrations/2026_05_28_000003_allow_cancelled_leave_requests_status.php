<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leave_requests MODIFY status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('leave_requests')->where('status', 'cancelled')->update(['status' => 'rejected']);
            DB::statement("ALTER TABLE leave_requests MODIFY status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
        }
    }
};
