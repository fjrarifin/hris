<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentVacancy extends Model
{
    protected $fillable = [
        'title',
        'department',
        'unit',
        'description',
        'status',
    ];

    public function candidates(): HasMany
    {
        return $this->hasMany(RecruitmentCandidate::class, 'vacancy_id');
    }
}
