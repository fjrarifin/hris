<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_penilaian_dtl', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penilaian_id');
            $table->string('nik_relasi', 50);

            $table->tinyInteger('f1'); // kedisiplinan
            $table->tinyInteger('f2'); // kerjasama
            $table->tinyInteger('f3'); // tanggung jawab
            $table->tinyInteger('f4'); // komunikasi
            $table->tinyInteger('f5'); // kinerja

            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('penilaian_id')->references('id')->on('t_penilaian_hdr')->onDelete('cascade');
            $table->index('nik_relasi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_penilaian_dtl');
    }
};
