<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_atk', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->string('nik');
            $table->string('nama_barang');
            $table->integer('qty')->default(1);
            $table->string('satuan')->default('pcs');
            $table->text('keterangan')->nullable();
            $table->date('tanggal_pengajuan');
            $table->enum('status', ['DRAFT', 'SUBMIT', 'APPROVED', 'REJECTED'])->default('SUBMIT');
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
            
            // Foreign key
            $table->foreign('nik')->references('nik')->on('m_karyawan')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_atk');
    }
};