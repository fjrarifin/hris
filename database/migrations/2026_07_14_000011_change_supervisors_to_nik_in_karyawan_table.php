<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add new NIK columns
        Schema::table('m_karyawan', function (Blueprint $table): void {
            $table->string('atasan_langsung_nik', 30)->nullable()->after('nama_atasan_langsung');
            $table->string('atasan_tidak_langsung_nik', 30)->nullable()->after('atasan_tidak_langsung');
        });

        // 2. Data Migration: Map supervisor names to NIKs
        $employees = DB::table('m_karyawan')->get();
        foreach ($employees as $employee) {
            $updates = [];

            if (!empty($employee->nama_atasan_langsung)) {
                $supervisor = DB::table('m_karyawan')
                    ->where('nama_karyawan', $employee->nama_atasan_langsung)
                    ->first();
                if ($supervisor) {
                    $updates['atasan_langsung_nik'] = $supervisor->nik;
                }
            }

            if (!empty($employee->atasan_tidak_langsung)) {
                $indirectSupervisor = DB::table('m_karyawan')
                    ->where('nama_karyawan', $employee->atasan_tidak_langsung)
                    ->first();
                if ($indirectSupervisor) {
                    $updates['atasan_tidak_langsung_nik'] = $indirectSupervisor->nik;
                }
            }

            if (!empty($updates)) {
                DB::table('m_karyawan')
                    ->where('nik', $employee->nik)
                    ->update($updates);
            }
        }
    }

    public function down(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table): void {
            $table->dropColumn(['atasan_langsung_nik', 'atasan_tidak_langsung_nik']);
        });
    }
};
