<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('m_karyawan_holding')) {
            Schema::create('m_karyawan_holding', function (Blueprint $table): void {
                $table->id();
                $table->string('nik', 50)->unique();
                $table->string('nama', 150);
                $table->string('jabatan', 100)->nullable();
                $table->string('departemen', 100)->nullable();
                $table->string('perusahaan', 150)->nullable();
                $table->string('no_hp', 30)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('t_qr_holding')) {
            Schema::create('t_qr_holding', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('m_karyawan_holding_id')->nullable()->index();
                $table->string('nik', 50)->index();
                $table->string('nama', 150);
                $table->string('perusahaan', 150)->nullable();
                $table->text('qr_payload');
                $table->string('access_date_code', 10)->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('generated_at')->useCurrent();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('t_qr_holding');
        Schema::dropIfExists('m_karyawan_holding');
    }
};
