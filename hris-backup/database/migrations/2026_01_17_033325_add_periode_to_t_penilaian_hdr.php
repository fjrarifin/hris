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
        Schema::table('t_penilaian_hdr', function (Blueprint $table) {
            $table->string('periode', 7)->after('tanggal'); // format: YYYY-MM
            $table->index(['nik_penilai', 'periode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_penilaian_hdr', function (Blueprint $table) {
            //
        });
    }
};
