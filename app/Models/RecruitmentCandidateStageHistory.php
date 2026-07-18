<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentCandidateStageHistory extends Model
{
    protected $fillable = [
        'candidate_id',
        'stage',
        'entered_at',
        'exited_at',
        'actor_user_id',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'exited_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
