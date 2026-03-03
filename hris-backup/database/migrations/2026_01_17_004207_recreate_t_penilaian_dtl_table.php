<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('t_penilaian_dtl');

        Schema::create('t_penilaian_dtl', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penilaian_id');
            $table->string('nik_relasi', 50);
            $table->unsignedBigInteger('faktor_id');
            $table->tinyInteger('nilai'); // 1-5
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('penilaian_id')->references('id')->on('t_penilaian_hdr')->onDelete('cascade');
            $table->foreign('faktor_id')->references('id')->on('m_penilaian_faktor')->onDelete('cascade');

            $table->index(['nik_relasi']);
            $table->index(['penilaian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_penilaian_dtl');
    }
};
