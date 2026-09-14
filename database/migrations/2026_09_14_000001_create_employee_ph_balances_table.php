<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel employee_ph_balances menyimpan saldo Public Holiday (PH) per karyawan.
     *
     * Berbeda dengan employee_ph_adjustments (untuk koreksi manual HR),
     * tabel ini adalah sumber kebenaran (source of truth) saldo PH yang berasal dari:
     *   - 'auto'   : digenerate otomatis saat payroll/absensi di-generate
     *               (karyawan hadir di tanggal public holiday → dapat +1 PH)
     *   - 'manual' : grant manual oleh HR (jika ada koreksi perlu langsung di saldo)
     *
     * Kalkulasi saldo PH yang baru:
     *   Sisa PH = SUM(employee_ph_balances.days WHERE karyawan = X)
     *           + SUM(employee_ph_adjustments.days WHERE karyawan = X)   ← koreksi HR lama tetap diperhitungkan
     *           - COUNT(public_holiday_requests WHERE user = X AND status tidak rejected/cancelled)
     */
    public function up(): void
    {
        if (! Schema::hasTable('employee_ph_balances')) {
            Schema::create('employee_ph_balances', function (Blueprint $table): void {
                $table->id();
                $table->string('karyawan_nik', 50)->index();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('public_holiday_id')->nullable()->constrained('public_holidays')->nullOnDelete();
                $table->date('holiday_date');                              // snapshot tanggal hari libur
                $table->string('holiday_name', 255);                      // snapshot nama hari libur
                $table->integer('days')->default(1);                       // biasanya +1, atau -1 untuk deduction
                $table->string('source', 50)->default('auto');             // 'auto' | 'manual'
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                // Satu karyawan hanya bisa punya 1 baris per public_holiday_id
                $table->unique(['karyawan_nik', 'public_holiday_id'], 'ph_balance_employee_holiday_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_ph_balances');
    }
};
