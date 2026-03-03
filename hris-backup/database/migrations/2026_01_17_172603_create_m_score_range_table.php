<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_score_range', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_score', 5, 2);
            $table->decimal('max_score', 5, 2);
            $table->string('label', 50); // Sangat Kurang, dst
            $table->tinyInteger('score_final'); // 1-5 (mapping)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_score_range');
    }
};
