<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table): void {
            if (! Schema::hasColumn('m_karyawan', 'nama_pemilik_rekening')) {
                $table->string('nama_pemilik_rekening', 150)->nullable()->after('no_rekening');
            }
        });
    }

    public function down(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table): void {
            if (Schema::hasColumn('m_karyawan', 'nama_pemilik_rekening')) {
                $table->dropColumn('nama_pemilik_rekening');
            }
        });
    }
};
