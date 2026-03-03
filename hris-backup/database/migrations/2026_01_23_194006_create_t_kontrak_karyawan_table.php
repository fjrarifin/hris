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
        Schema::create('t_kontrak_karyawan', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 30);
            $table->integer('kontrak_ke')->default(1);

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->integer('durasi_bulan')->nullable();
            $table->string('status_kontrak', 20)->default('AKTIF'); // AKTIF / SELESAI
            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->index(['nik']);
            $table->unique(['nik', 'kontrak_ke']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_kontrak_karyawan');
    }
};
