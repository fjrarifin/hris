<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentCandidateCaseStudy extends Model
{
    protected $table = 'recruitment_candidate_case_studies';

    protected $fillable = [
        'candidate_id',
        'round',
        'title',
        'case_study_document_path',
        'case_study_link',
        'case_study_sent_at',
        'case_study_wa_sent_at',
        'case_study_token',
        'case_study_password',
        'case_study_submitted_file_path',
        'case_study_submitted_at',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'round' => 'integer',
        'case_study_sent_at' => 'datetime',
        'case_study_wa_sent_at' => 'datetime',
        'case_study_submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }
}
