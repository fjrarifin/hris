<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentCandidateApplicationHistory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'snapshot_data' => 'array',
        'applied_at' => 'datetime',
        'case_study_submitted_at' => 'datetime',
        'offering_letter_signed_at' => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(RecruitmentVacancy::class, 'vacancy_id');
    }
}
