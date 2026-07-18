<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->string('offering_letter_token', 100)->nullable()->unique()->after('offering_letter_path');
            $table->timestamp('offering_letter_sent_at')->nullable()->after('offering_letter_token');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->dropUnique(['offering_letter_token']);
            $table->dropColumn(['offering_letter_token', 'offering_letter_sent_at']);
        });
    }
};
