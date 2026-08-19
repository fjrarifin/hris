<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_absen')) {
            Schema::create('event_absen', function (Blueprint $table) {
                $table->id();
                $table->string('nama_event');
                $table->text('deskripsi')->nullable();
                $table->string('slug')->unique();
                $table->dateTime('tanggal_mulai');
                $table->dateTime('tanggal_selesai');
                $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('absensi_event')) {
            Schema::create('absensi_event', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_event_absen')->constrained('event_absen')->cascadeOnDelete();
                $table->string('nik_karyawan');
                $table->string('foto_absen');
                $table->timestamp('jam_absen');
                $table->text('user_agent')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->unique(['id_event_absen', 'nik_karyawan'], 'absensi_event_unique_user');
                $table->index('nik_karyawan');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_event');
        Schema::dropIfExists('event_absen');
    }
};
