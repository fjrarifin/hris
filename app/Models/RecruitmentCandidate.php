<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentCandidate extends Model
{
    protected $with = ['userInterviewEvaluations.interviewer'];

    protected $hidden = [
        'reference_check_password',
        'offering_letter_token',
        'offering_letter_password',
        'case_study_password',
    ];

    protected $fillable = [
        'profile_candidate_id',
        'vacancy_id',
        'name',
        'email',
        'phone',
        'resume_path',
        'status',
        'notes',
        'expected_salary',
        'education_level',
        'education_major',
        'marital_status',
        'known_person',
        'referred_from',
        'pic_nik',
        'atasan_langsung_nik',
        'photo_path',

        'interview_date',
        'interview_time',
        'interviewer_nik',
        'interview_type',
        'interview_location',
        'interview_meet_link',
        'interview_is_locked',
        'offering_letter_path',
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

        // HR Interview
        'interview_hr_date',
        'interview_hr_time',
        'interview_hr_type',
        'interview_hr_location',
        'interview_hr_meet_link',
        'interview_hr_completed_at',
        'interview_hr_completed_by',
        'interview_hr_summary_path',
        'interview_hr_text_summary',
        'interview_hr_email_sent_at',
        'interview_hr_wa_sent_at',
        'interview_hr_wa_sent_date',
        'interview_hr_wa_sent_time',
        'interview_hr_wa_sent_type',
        'interview_hr_prev_date',
        'interview_hr_prev_time',

        // Case Study
        'case_study_document_path',
        'case_study_link',
        'case_study_sent_at',
        'case_study_submitted_file_path',
        'case_study_submitted_at',
        'case_study_wa_sent_at',
        'case_study_token',
        'case_study_password',

        // Reference Check
        'reference_check_token',
        'reference_check_password',
        'reference_check_summary_path',
        'reference_check_email_sent_at',
        'reference_check_wa_sent_at',
        'reference_check_submitted_at',

        // Offering Letter Extra
        'last_company',
        'offered_salary',
        'join_date',
        'previous_salary',
        'offering_letter_token',
        'offering_letter_password',
        'offering_letter_sent_at',
        'offering_letter_wa_sent_at',
        'offering_letter_signed_path',
        'offering_letter_signature_data',
        'offering_letter_signed_at',

        // Onboarding
        'onboarding_token',
        'onboarding_password',
        'onboarding_sent_at',
        'onboarding_wa_sent_at',
        'onboarding_completed_at',
        'onboarding_data',
        'employee_nik',
    ];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(RecruitmentVacancy::class, 'vacancy_id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'interviewer_nik', 'nik');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'pic_nik', 'nik');
    }

    public function userInterviews()
    {
        return $this->hasMany(RecruitmentCandidateUserInterview::class, 'candidate_id');
    }

    public function userInterviewEvaluations()
    {
        return $this->hasMany(RecruitmentUserInterviewEvaluation::class, 'candidate_id');
    }

    public function references()
    {
        return $this->hasMany(RecruitmentCandidateReference::class, 'candidate_id');
    }

    public function pkbSigners()
    {
        return $this->hasMany(RecruitmentCandidatePkbSigner::class, 'candidate_id');
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(RecruitmentCandidateStageHistory::class, 'candidate_id');
    }

    protected $appends = ['interviewers'];

    protected $casts = [
        'join_date' => 'date',
        'interview_hr_completed_at' => 'datetime',
        'offering_letter_sent_at' => 'datetime',
        'offering_letter_wa_sent_at' => 'datetime',
        'offering_letter_signed_at' => 'datetime',
        'reference_check_email_sent_at' => 'datetime',
        'reference_check_wa_sent_at' => 'datetime',
        'reference_check_submitted_at' => 'datetime',
        'onboarding_sent_at' => 'datetime',
        'onboarding_wa_sent_at' => 'datetime',
        'onboarding_data' => 'array',
    ];

    public function getInterviewersAttribute(): \Illuminate\Database\Eloquent\Collection
    {
        if (empty($this->interviewer_nik)) {
            return new \Illuminate\Database\Eloquent\Collection;
        }
        $niks = str_contains($this->interviewer_nik, ',')
            ? array_map('trim', explode(',', $this->interviewer_nik))
            : [trim($this->interviewer_nik)];

        return Karyawan::whereIn('nik', $niks)->get();
    }

    public function applicationHistories(): HasMany
    {
        return $this->hasMany(RecruitmentCandidateApplicationHistory::class, 'candidate_id')->latest('created_at');
    }

    /**
     * Lamaran sebelumnya yang terhubung ke kandidat ini sebagai profil utama.
     * (Kandidat lama yang di-link saat re-apply)
     */
    public function linkedApplications(): HasMany
    {
        return $this->hasMany(self::class, 'profile_candidate_id')->latest('created_at');
    }

    public function atasanLangsung(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'atasan_langsung_nik', 'nik');
    }
}

