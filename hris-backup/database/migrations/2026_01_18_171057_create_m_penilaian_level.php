<?php

// database/migrations/xxxx_xx_xx_create_m_penilaian_level_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_penilaian_level', function (Blueprint $table) {
            $table->id();
            $table->string('nama_level')->unique(); // GM, Sr. Manager, dll
            $table->unsignedTinyInteger('indikator_total')->default(5); // 5/6
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_penilaian_level');
    }
};
