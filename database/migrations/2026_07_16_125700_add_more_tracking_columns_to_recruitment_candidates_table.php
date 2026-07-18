<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->string('interview_hr_wa_sent_time')->nullable();
            $table->string('interview_hr_wa_sent_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropColumn(['interview_hr_wa_sent_time', 'interview_hr_wa_sent_type']);
        });
    }
};
