<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            // Link lamaran lama ke profil utama kandidat.
            // NULL  = ini adalah profil utama (tampil di daftar HR)
            // NOT NULL = ini adalah lamaran lama yang terhubung ke profil utama
            $table->unsignedBigInteger('profile_candidate_id')
                ->nullable()
                ->after('id')
                ->comment('FK ke recruitment_candidates.id profil utama. NULL = ini profil utama.');

            $table->foreign('profile_candidate_id')
                ->references('id')
                ->on('recruitment_candidates')
                ->nullOnDelete();

            $table->index('profile_candidate_id');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropForeign(['profile_candidate_id']);
            $table->dropIndex(['profile_candidate_id']);
            $table->dropColumn('profile_candidate_id');
        });
    }
};
