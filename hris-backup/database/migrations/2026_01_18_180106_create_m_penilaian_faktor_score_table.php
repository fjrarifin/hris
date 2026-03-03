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
        Schema::create('m_penilaian_faktor_score', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('faktor_id');
            $table->tinyInteger('score'); // 1..5
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            $table->unique(['faktor_id', 'score']);
            $table->foreign('faktor_id')->references('id')->on('m_penilaian_faktor')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m_penilaian_faktor_score');
    }
};
