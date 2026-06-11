<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_corrections', function (Blueprint $table): void {
            $table->string('correction_type', 30)->default('time')->after('attendance_date');
            $table->string('absence_type')->nullable()->after('corrected_by');
            $table->unsignedBigInteger('absence_id')->nullable()->after('absence_type');
            $table->foreignId('leave_accrual_id')->nullable()->after('absence_id')->constrained('leave_accruals')->nullOnDelete();
            $table->index(['absence_type', 'absence_id'], 'attendance_corrections_absence_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_corrections', function (Blueprint $table): void {
            $table->dropIndex('attendance_corrections_absence_idx');
            $table->dropConstrainedForeignId('leave_accrual_id');
            $table->dropColumn(['correction_type', 'absence_type', 'absence_id']);
        });
    }
};
