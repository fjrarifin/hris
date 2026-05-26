<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('t_kontrak_karyawan')) {
            Schema::table('t_kontrak_karyawan', function (Blueprint $table): void {
                if (! Schema::hasColumn('t_kontrak_karyawan', 'jenis_kontrak')) {
                    $table->string('jenis_kontrak', 10)->nullable()->after('kontrak_ke');
                }

                if (! Schema::hasColumn('t_kontrak_karyawan', 'keterangan')) {
                    $table->text('keterangan')->nullable()->after('status_kontrak');
                }
            });

            DB::table('t_kontrak_karyawan')
                ->whereNull('jenis_kontrak')
                ->update(['jenis_kontrak' => 'PKWT']);

            DB::table('t_kontrak_karyawan')
                ->where('status_kontrak', 'DIPERPANJANG')
                ->update(['status_kontrak' => 'AKTIF']);

            DB::table('t_kontrak_karyawan')
                ->whereIn('status_kontrak', ['SELESAI', 'HABIS', 'EXPIRED'])
                ->update(['status_kontrak' => 'NONAKTIF']);
        }

        if (Schema::hasTable('m_karyawan')) {
            Schema::table('m_karyawan', function (Blueprint $table): void {
                if (! Schema::hasColumn('m_karyawan', 'status_pajak')) {
                    $table->string('status_pajak', 10)->nullable()->after('no_npwp');
                }

                if (! Schema::hasColumn('m_karyawan', 'nama_anak_1')) {
                    $table->string('nama_anak_1', 150)->nullable()->after('jumlah_anak');
                }

                if (! Schema::hasColumn('m_karyawan', 'nama_anak_2')) {
                    $table->string('nama_anak_2', 150)->nullable()->after('nama_anak_1');
                }

                if (! Schema::hasColumn('m_karyawan', 'nama_anak_3')) {
                    $table->string('nama_anak_3', 150)->nullable()->after('nama_anak_2');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('t_kontrak_karyawan')) {
            Schema::table('t_kontrak_karyawan', function (Blueprint $table): void {
                foreach (['jenis_kontrak', 'keterangan'] as $column) {
                    if (Schema::hasColumn('t_kontrak_karyawan', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('m_karyawan')) {
            Schema::table('m_karyawan', function (Blueprint $table): void {
                foreach (['status_pajak', 'nama_anak_1', 'nama_anak_2', 'nama_anak_3'] as $column) {
                    if (Schema::hasColumn('m_karyawan', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
