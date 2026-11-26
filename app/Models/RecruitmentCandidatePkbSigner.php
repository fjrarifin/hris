<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RecruitmentCandidatePkbSigner extends Model
{
    protected $table = 'recruitment_candidate_pkb_signers';

    protected static function booted(): void
    {
        static::creating(function (self $signer): void {
            if (empty($signer->public_token)) {
                $signer->public_token = Str::random(48);
            }
        });
    }

    protected $fillable = [
        'candidate_id',
        'employee_nik',
        'public_token',
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
