<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentRequest extends Model
{
    protected $fillable = [
        'requester_nik',
        'title',
        'department',
        'unit',
        'quantity',
        'description',
        'status',
        'vacancy_id',
        'hrd_notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'requester_nik', 'nik');
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(RecruitmentVacancy::class, 'vacancy_id');
    }
}
