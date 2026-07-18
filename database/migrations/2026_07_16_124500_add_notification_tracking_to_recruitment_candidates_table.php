<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->timestamp('interview_hr_email_sent_at')->nullable();
            $table->timestamp('interview_hr_wa_sent_at')->nullable();
            $table->date('interview_hr_wa_sent_date')->nullable();
            $table->date('interview_hr_prev_date')->nullable();
            $table->string('interview_hr_prev_time')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropColumn([
                'interview_hr_email_sent_at',
                'interview_hr_wa_sent_at',
                'interview_hr_wa_sent_date',
                'interview_hr_prev_date',
                'interview_hr_prev_time'
            ]);
        });
    }
};
