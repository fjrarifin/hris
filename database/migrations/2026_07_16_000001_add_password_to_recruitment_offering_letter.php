<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->string('offering_letter_password')->nullable()->after('offering_letter_token');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->dropColumn('offering_letter_password');
        });
    }
};
