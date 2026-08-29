<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone', 30)->nullable()->after('email')->index();
            });
        }

        // Otomatis sinkronkan nomor telepon akun dari tabel m_karyawan
        try {
            DB::statement("
                UPDATE users u
                JOIN m_karyawan k ON u.username = k.nik
                SET u.phone = k.no_hp
                WHERE u.phone IS NULL AND k.no_hp IS NOT NULL
            ");
        } catch (\Throwable $e) {
            // ignore if sql fail
        }

        // Pasang nomor telepon untuk akun IT Administrator (Level 0)
        try {
            DB::table('users')
                ->where('username', 'it')
                ->orWhere('level', 0)
                ->update(['phone' => '082117289833']);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }
};
