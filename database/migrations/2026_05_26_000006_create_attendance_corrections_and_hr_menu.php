<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_corrections')) {
            Schema::create('attendance_corrections', function (Blueprint $table): void {
                $table->id();
                $table->string('nik', 30);
                $table->date('attendance_date');
                $table->time('corrected_scan_in')->nullable();
                $table->time('corrected_scan_out')->nullable();
                $table->boolean('has_missing_attendance_form')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['nik', 'attendance_date'], 'attendance_corrections_employee_date_unique');
                $table->index('attendance_date');
            });
        }

        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')->insertOrIgnore([
                'key' => 'hr-attendance-corrections',
                'label' => 'Koreksi Absensi',
                'path' => '/hr/attendance-corrections',
                'icon' => 'i-lucide-clipboard-pen-line',
                'allowed_levels' => '2',
                'sort_order' => 35,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')->where('key', 'hr-attendance-corrections')->delete();
        }

        Schema::dropIfExists('attendance_corrections');
    }
};
