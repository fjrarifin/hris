<?php

// database/migrations/xxxx_xx_xx_create_m_penilaian_template_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_penilaian_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained('m_penilaian_level')->cascadeOnDelete();
            $table->foreignId('faktor_id')->constrained('m_penilaian_faktor')->cascadeOnDelete();

            $table->unsignedTinyInteger('urutan')->default(1);
            $table->decimal('bobot', 5, 2)->default(1.00); // optional

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['level_id', 'faktor_id']); // agar tidak dobel
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_penilaian_template');
    }
};
