<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidate_references', function (Blueprint $table): void {
            $table->string('public_code', 20)->nullable()->unique()->after('public_token');
        });

        DB::table('recruitment_candidate_references')->whereNotNull('public_token')->whereNull('submitted_at')
            ->orderBy('id')->eachById(function (object $reference): void {
                do {
                    $code = Str::random(12);
                } while (DB::table('recruitment_candidate_references')->where('public_code', $code)->exists());
                DB::table('recruitment_candidate_references')->where('id', $reference->id)->update(['public_code' => $code]);
            });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidate_references', function (Blueprint $table): void {
            $table->dropUnique(['public_code']);
            $table->dropColumn('public_code');
        });
    }
};
