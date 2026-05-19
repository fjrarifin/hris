<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_permissions', 'hr_approved_at')) {
                $table->timestamp('hr_approved_at')->nullable()->after('manager_approved_by');
            }

            if (! Schema::hasColumn('employee_permissions', 'hr_approved_by')) {
                $table->unsignedBigInteger('hr_approved_by')->nullable()->after('hr_approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_permissions', function (Blueprint $table) {
            foreach (['hr_approved_at', 'hr_approved_by'] as $column) {
                if (Schema::hasColumn('employee_permissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
