<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_penilaian_faktor', function (Blueprint $table) {
            $table->id();
            $table->string('nama_faktor', 150);
            $table->text('deskripsi')->nullable();
            $table->integer('urutan')->default(1);
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_penilaian_faktor');
    }
};
