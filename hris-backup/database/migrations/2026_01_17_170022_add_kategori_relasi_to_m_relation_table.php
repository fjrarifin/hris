<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_relation', function (Blueprint $table) {
            $table->string('kategori_relasi', 20)
                ->default('Peer')
                ->after('nik_relasi');
        });
    }

    public function down(): void
    {
        Schema::table('m_relation', function (Blueprint $table) {
            $table->dropColumn('kategori_relasi');
        });
    }
};
