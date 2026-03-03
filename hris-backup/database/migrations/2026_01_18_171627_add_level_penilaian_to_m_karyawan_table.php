<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // migration alter m_karyawan
        Schema::table('m_karyawan', function (Blueprint $table) {
            $table->string('level_penilaian')->nullable()->after('jabatan');
            // contoh isi: "GM", "Sr. Manager", "Jr. Staff", dst
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('m_karyawan', function (Blueprint $table) {
            //
        });
    }
};
