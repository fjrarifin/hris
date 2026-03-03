<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_penilaian_hdr', function (Blueprint $table) {
            $table->id();
            $table->string('nik_penilai', 50);
            $table->date('tanggal')->default(now());
            $table->integer('total_relasi')->default(0);
            $table->timestamps();

            $table->index('nik_penilai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_penilaian_hdr');
    }
};
