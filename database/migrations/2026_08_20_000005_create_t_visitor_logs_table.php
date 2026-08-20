<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('t_visitor_logs')) {
            Schema::create('t_visitor_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('nomor_kunjungan', 50)->unique();
                $table->string('nomor_identitas', 50)->index();
                $table->string('nama_visitor', 150);
                $table->string('no_hp', 30)->nullable();
                $table->string('instansi', 150)->nullable();
                $table->string('tujuan_bertemu', 150)->nullable();
                $table->text('keperluan');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('waktu_masuk')->useCurrent();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('t_visitor_logs');
    }
};
