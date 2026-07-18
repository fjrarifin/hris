<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentCandidateReference extends Model
{
    protected $table = 'recruitment_candidate_references';

    protected $fillable = [
        'candidate_id',
        'name',
        'phone',
        'company',
        'position',
        'relationship',
        'form_type',
        'public_token',
        'public_code',
        'answers',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return ['answers' => 'array', 'submitted_at' => 'datetime'];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }
}
