<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_daily_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('karyawan_nik', 30);
            $table->date('schedule_date');
            $table->foreignId('schedule_category_id')->nullable()->constrained('attendance_schedule_categories')->nullOnDelete();
            $table->string('schedule_code', 20);
            $table->string('source', 30)->default('manual');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['karyawan_nik', 'schedule_date'], 'employee_daily_schedules_unique');
            $table->index(['schedule_date', 'schedule_code'], 'employee_daily_schedules_date_code_index');
            $table->index('karyawan_nik', 'employee_daily_schedules_nik_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_daily_schedules');
    }
};
