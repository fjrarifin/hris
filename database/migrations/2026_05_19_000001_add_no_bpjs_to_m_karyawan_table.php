<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table) {
            if (! Schema::hasColumn('m_karyawan', 'no_bpjs')) {
                $table->string('no_bpjs', 50)->nullable()->after('bpjs');
            }
        });
    }

    public function down(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table) {
            if (Schema::hasColumn('m_karyawan', 'no_bpjs')) {
                $table->dropColumn('no_bpjs');
            }
        });
    }
};
