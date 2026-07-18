<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->timestamp('offering_letter_wa_sent_at')->nullable()->after('offering_letter_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropColumn('offering_letter_wa_sent_at');
        });
    }
};
