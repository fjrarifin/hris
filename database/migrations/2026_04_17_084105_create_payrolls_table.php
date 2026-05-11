<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();

            // relasi ke karyawan
            $table->unsignedBigInteger('karyawan_id');

            // periode
            $table->date('periode_start');
            $table->date('periode_end');

            // absensi
            $table->integer('hari_kerja')->default(0);
            $table->integer('hadir')->default(0);
            $table->integer('libur')->default(0);

            // total akhir
            $table->bigInteger('total_pendapatan')->default(0);
            $table->bigInteger('total_potongan')->default(0);
            $table->bigInteger('total_dibayarkan')->default(0);

            $table->timestamps();

            $table->foreign('karyawan_id')->references('id')->on('m_karyawan')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
