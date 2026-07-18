<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentCandidateUserInterview extends Model
{
    protected $table = 'recruitment_candidate_user_interviews';

    protected $fillable = [
        'candidate_id',
        'round',
        'interview_date',
        'interview_time',
        'interviewer_nik',
        'interview_type',
        'interview_location',
        'interview_meet_link',
        'completed_at',
        'completed_by',
        'summary_path',
        
        // Evaluations
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
        'email_sent_at',
        'wa_sent_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'wa_sent_at' => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'interviewer_nik', 'nik');
    }

    protected $appends = ['interviewers'];

    public function getInterviewersAttribute(): \Illuminate\Database\Eloquent\Collection
    {
        if (empty($this->interviewer_nik)) {
            return new \Illuminate\Database\Eloquent\Collection();
        }
        $niks = str_contains($this->interviewer_nik, ',')
            ? array_map('trim', explode(',', $this->interviewer_nik))
            : [trim($this->interviewer_nik)];
            
        return Karyawan::whereIn('nik', $niks)->get();
    }
}
