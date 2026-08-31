<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('m_karyawan') && ! Schema::hasColumn('m_karyawan', 'bisnis_unit')) {
            Schema::table('m_karyawan', function (Blueprint $table): void {
                $table->string('bisnis_unit', 100)->nullable()->after('posisi_title');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('m_karyawan') && Schema::hasColumn('m_karyawan', 'bisnis_unit')) {
            Schema::table('m_karyawan', function (Blueprint $table): void {
                $table->dropColumn('bisnis_unit');
            });
        }
    }
};
