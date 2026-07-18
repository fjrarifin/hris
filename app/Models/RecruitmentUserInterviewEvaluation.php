<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentUserInterviewEvaluation extends Model
{
    protected $table = 'recruitment_user_interview_evaluations';

    protected $fillable = [
        'candidate_id',
        'round',
        'interviewer_nik',
        'token',
        'sent_at',
        
        // Aspek Penilaian
        'interview_appearance',
        'interview_attitude',
        'interview_communication',
        'interview_motivation',
        'interview_initiative',
        'interview_teamwork',
        'interview_domain_experience',
        'interview_general_knowledge',
        'interview_growth_potential',
        
        'interview_total_score',
        'interview_evaluation_notes',
        'interview_recommendation',
        'submitted_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'interviewer_nik', 'nik');
    }
}
