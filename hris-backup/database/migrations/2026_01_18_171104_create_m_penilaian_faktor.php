<?php

// database/migrations/xxxx_xx_xx_create_m_penilaian_faktor_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_penilaian_faktor', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->nullable()->unique(); // optional, misal: DISCIPLINE
            $table->string('nama_faktor');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_penilaian_faktor');
    }
};
