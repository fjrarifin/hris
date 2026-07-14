<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentCandidate extends Model
{
    protected $fillable = [
        'vacancy_id',
        'name',
        'email',
        'phone',
        'resume_path',
        'status',
        'notes',
    ];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(RecruitmentVacancy::class, 'vacancy_id');
    }
}
