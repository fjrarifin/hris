<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payroll_items MODIFY type ENUM('earning','deduction','employer_contribution') NOT NULL");
        }
    }

    public function down(): void
    {
        // Existing snapshots may already use employer_contribution after deployment.
    }
};
