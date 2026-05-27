<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table): void {
            if (! Schema::hasColumn('m_karyawan', 'golongan_darah')) {
                $table->string('golongan_darah', 3)->nullable()->after('jenis_kelamin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table): void {
            if (Schema::hasColumn('m_karyawan', 'golongan_darah')) {
                $table->dropColumn('golongan_darah');
            }
        });
    }
};
