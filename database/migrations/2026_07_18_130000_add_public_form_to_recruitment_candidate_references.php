<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidate_references', function (Blueprint $table): void {
            $table->string('form_type', 20)->default('staff')->after('relationship');
            $table->string('public_token', 100)->nullable()->unique()->after('form_type');
            $table->json('answers')->nullable()->after('public_token');
            $table->timestamp('submitted_at')->nullable()->after('answers');
        });

        DB::table('recruitment_candidate_references as references')
            ->leftJoin('recruitment_candidates as candidates', 'candidates.id', '=', 'references.candidate_id')
            ->leftJoin('recruitment_vacancies as vacancies', 'vacancies.id', '=', 'candidates.vacancy_id')
            ->select(['references.id', 'vacancies.title', 'vacancies.position'])
            ->orderBy('references.id')
            ->each(function (object $reference): void {
                $position = mb_strtolower((string) ($reference->position ?: $reference->title));
                $managerial = preg_match('/\b(leader|supervisor|spv|assistant\s+manager|asst\.?\s+manager|manager|general\s+manager|gm|head|director|direktur)\b/u', $position) === 1;
                DB::table('recruitment_candidate_references')->where('id', $reference->id)->update([
                    'form_type' => $managerial ? 'managerial' : 'staff',
                    'public_token' => Str::random(64),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidate_references', function (Blueprint $table): void {
            $table->dropUnique(['public_token']);
            $table->dropColumn(['form_type', 'public_token', 'answers', 'submitted_at']);
        });
    }
};
