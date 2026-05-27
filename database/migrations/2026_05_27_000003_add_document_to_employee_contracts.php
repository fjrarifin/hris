<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('t_kontrak_karyawan') && ! Schema::hasColumn('t_kontrak_karyawan', 'document')) {
            Schema::table('t_kontrak_karyawan', function (Blueprint $table): void {
                $table->string('document')->nullable()->after('keterangan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('t_kontrak_karyawan') && Schema::hasColumn('t_kontrak_karyawan', 'document')) {
            Schema::table('t_kontrak_karyawan', function (Blueprint $table): void {
                $table->dropColumn('document');
            });
        }
    }
};
