<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentCandidatePkbSigner extends Model
{
    protected $table = 'recruitment_candidate_pkb_signers';

    protected $fillable = [
        'candidate_id',
        'employee_nik',
        'sent_at',
        'signed_at',
        'signature_data',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'employee_nik', 'nik');
    }
}
