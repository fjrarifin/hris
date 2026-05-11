<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('payroll_new', function (Blueprint $table) {
            $table->id();

            $table->string('nik')->nullable();
            $table->date('periode_start')->nullable();
            $table->date('periode_end')->nullable();

            // 🔥 semua data mentah CSV
            $table->json('raw_data')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_new');
    }
};
