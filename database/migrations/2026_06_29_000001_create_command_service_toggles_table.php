<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_service_toggles', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('command_service_toggles')->insertOrIgnore([
            [
                'key' => 'attendance:send-employee-warnings',
                'label' => 'Kirim peringatan absensi tidak lengkap',
                'description' => 'Toggle untuk perintah daily yang mengirim peringatan pribadi absensi tidak lengkap ke karyawan dan atasan.',
                'is_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'attendance:send-incomplete-report',
                'label' => 'Kirim laporan absensi tidak lengkap',
                'description' => 'Toggle untuk perintah daily yang mengirim laporan absensi tidak lengkap ke grup attendance.',
                'is_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('command_service_toggles');
    }
};