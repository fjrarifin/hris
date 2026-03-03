<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_rekomendasi_score', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('score')->unique(); // 1-5
            $table->string('label', 50); // Sangat Kurang, Kurang, dst
            $table->text('deskripsi_umum')->nullable();
            $table->longText('rekomendasi_pengembangan')->nullable(); // simpan list bullet (pakai \n)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_rekomendasi_score');
    }
};
