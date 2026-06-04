<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_payroll_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('karyawan_nik', 30)->unique();
            $table->unsignedBigInteger('gaji_pokok')->default(0);
            $table->unsignedBigInteger('tunjangan_jabatan')->default(0);
            $table->unsignedBigInteger('tunjangan_tidak_tetap')->default(0);
            $table->unsignedBigInteger('dasar_bpjs')->default(0);
            $table->unsignedBigInteger('dasar_jp')->default(0);
            $table->decimal('rate_jkk_percent', 5, 2)->default(0.54);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_profiles');
    }
};
