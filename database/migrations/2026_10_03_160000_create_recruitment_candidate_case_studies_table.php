<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recruitment_candidate_case_studies')) {
            Schema::create('recruitment_candidate_case_studies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('candidate_id')->constrained('recruitment_candidates')->onDelete('cascade');
                $table->unsignedTinyInteger('round')->default(1);
                $table->string('title', 100)->nullable();
                $table->string('case_study_document_path', 255)->nullable();
                $table->string('case_study_link', 255)->nullable();
                $table->timestamp('case_study_sent_at')->nullable();
                $table->timestamp('case_study_wa_sent_at')->nullable();
                $table->string('case_study_token', 100)->nullable()->index();
                $table->string('case_study_password')->nullable();
                $table->string('case_study_submitted_file_path', 255)->nullable();
                $table->timestamp('case_study_submitted_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['candidate_id', 'round']);
            });

            // Backfill existing candidate case study data
            $candidates = DB::table('recruitment_candidates')
                ->whereNotNull('case_study_sent_at')
                ->orWhereNotNull('case_study_submitted_file_path')
                ->orWhereNotNull('case_study_document_path')
                ->orWhereNotNull('case_study_token')
                ->get();

            foreach ($candidates as $candidate) {
                DB::table('recruitment_candidate_case_studies')->updateOrInsert(
                    [
                        'candidate_id' => $candidate->id,
                        'round' => 1,
                    ],
                    [
                        'title' => 'Tahap 1',
                        'case_study_document_path' => $candidate->case_study_document_path,
                        'case_study_link' => $candidate->case_study_link,
                        'case_study_sent_at' => $candidate->case_study_sent_at,
                        'case_study_wa_sent_at' => $candidate->case_study_wa_sent_at,
                        'case_study_token' => $candidate->case_study_token,
                        'case_study_password' => $candidate->case_study_password,
                        'case_study_submitted_file_path' => $candidate->case_study_submitted_file_path,
                        'case_study_submitted_at' => $candidate->case_study_submitted_at,
                        'completed_at' => $candidate->case_study_submitted_at,
                        'created_at' => $candidate->case_study_sent_at ?? now(),
                        'updated_at' => $candidate->case_study_submitted_at ?? now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_candidate_case_studies');
    }
};
