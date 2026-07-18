<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->timestamp('reference_check_email_sent_at')->nullable()->after('reference_check_summary_path');
            $table->timestamp('reference_check_wa_sent_at')->nullable()->after('reference_check_email_sent_at');
            $table->timestamp('reference_check_submitted_at')->nullable()->after('reference_check_wa_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropColumn([
                'reference_check_email_sent_at',
                'reference_check_wa_sent_at',
                'reference_check_submitted_at',
            ]);
        });
    }
};
