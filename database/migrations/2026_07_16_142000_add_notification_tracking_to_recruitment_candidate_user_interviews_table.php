<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recruitment_candidate_user_interviews', function (Blueprint $table) {
            $table->timestamp('email_sent_at')->nullable()->after('interview_recommendation');
            $table->timestamp('wa_sent_at')->nullable()->after('email_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recruitment_candidate_user_interviews', function (Blueprint $table) {
            $table->dropColumn(['email_sent_at', 'wa_sent_at']);
        });
    }
};
