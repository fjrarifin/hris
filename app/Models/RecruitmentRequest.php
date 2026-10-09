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
        'hiring_type',
        'replaced_employee_nik',
        'replaced_employee_name',
        'replaced_employee_position',
        'employment_status',
        'direct_report_nik',
        'direct_report_name',
        'subordinates',
        'requires_gm',
        'approval_step',
        'manager_nik',
        'manager_name',
        'manager_status',
        'manager_approved_at',
        'manager_notes',
        'gm_nik',
        'gm_name',
        'gm_status',
        'gm_approved_at',
        'gm_notes',
        'hrbp_nik',
        'hrbp_name',
        'hrbp_status',
        'hrbp_approved_at',
        'hrbp_notes',
        'approval_token',
        'approval_token_expires_at',
        'hiring_status',
        'request_date',
        'start_date',
        'finished_date',
        'pic_hiring_nik',
        'pic_hiring_name',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'subordinates' => 'array',
        'requires_gm' => 'boolean',
        'manager_approved_at' => 'datetime',
        'gm_approved_at' => 'datetime',
        'hrbp_approved_at' => 'datetime',
        'approval_token_expires_at' => 'datetime',
        'request_date' => 'date',
        'start_date' => 'date',
        'finished_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_nik', 'username');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'requester_nik', 'nik');
    }

    public function replacedEmployee(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'replaced_employee_nik', 'nik');
    }

    public function directReport(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'direct_report_nik', 'nik');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'manager_nik', 'nik');
    }

    public function gm(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'gm_nik', 'nik');
    }

    public function hrbp(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'hrbp_nik', 'nik');
    }

    public function picHiring(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'pic_hiring_nik', 'nik');
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(RecruitmentVacancy::class, 'vacancy_id');
    }
}
