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
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('payroll_id');

            // earning / deduction
            $table->enum('type', ['earning', 'deduction']);

            // contoh: gaji pokok, lembur, pph21
            $table->string('nama_item');

            $table->bigInteger('amount')->default(0);

            $table->timestamps();

            $table->foreign('payroll_id')->references('id')->on('payrolls')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
