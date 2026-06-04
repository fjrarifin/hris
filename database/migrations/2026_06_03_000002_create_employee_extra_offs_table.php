<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_extra_offs')) {
            Schema::create('employee_extra_offs', function (Blueprint $table): void {
                $table->id();
                $table->string('karyawan_nik', 30);
                $table->date('periode_start');
                $table->date('periode_end');
                $table->unsignedInteger('days')->default(0);
                $table->string('source', 50)->default('payroll');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['karyawan_nik', 'periode_start', 'periode_end'], 'extra_off_employee_period_unique');
                $table->index('karyawan_nik', 'extra_off_employee_index');
            });
        }

        if (Schema::hasTable('employee_extra_offs') && ! $this->hasIndex('extra_off_employee_period_unique')) {
            Schema::table('employee_extra_offs', function (Blueprint $table): void {
                $table->unique(['karyawan_nik', 'periode_start', 'periode_end'], 'extra_off_employee_period_unique');
            });
        }

        if (Schema::hasTable('employee_extra_offs') && ! $this->hasIndex('extra_off_employee_index')) {
            Schema::table('employee_extra_offs', function (Blueprint $table): void {
                $table->index('karyawan_nik', 'extra_off_employee_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_extra_offs');
    }

    private function hasIndex(string $indexName): bool
    {
        return DB::select('SHOW INDEX FROM employee_extra_offs WHERE Key_name = ?', [$indexName]) !== [];
    }
};
