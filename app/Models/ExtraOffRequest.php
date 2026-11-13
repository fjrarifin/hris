<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraOffRequest extends Model
{
    protected $fillable = [
        'user_id',
        'employee_extra_off_id',
        'source_period_start',
        'source_period_end',
        'claim_date',
        'status',
        'manager_approved_at',
        'manager_approved_by',
        'hr_approved_at',
        'hr_approved_by',
        'reject_reason',
        'approval_token',
        'approval_token_expires_at',
    ];

    protected $casts = [
        'source_period_start' => 'date',
        'source_period_end' => 'date',
        'claim_date' => 'date',
        'manager_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
        'approval_token_expires_at' => 'datetime',
    ];

    public function assertReplacementIsValid(): void
    {
        if (! $this->employee_extra_off_id) {
            return;
        }
        $source = EmployeeExtraOff::find($this->employee_extra_off_id);
        if ($source?->public_holiday_request_id && ($this->created_at ?? now())->gt($source->expirationDate())) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'decision' => 'Extra Off pengganti PH sudah melewati masa berlaku.',
            ]);
        }
    }

    public function scopeForSource($query, EmployeeExtraOff $source)
    {
        return $query->where(function ($query) use ($source) {
            $query->where('employee_extra_off_id', $source->id);
            if (! $source->public_holiday_request_id) {
                $query->orWhere(function ($legacy) use ($source) {
                    $legacy->whereNull('employee_extra_off_id')
                        ->whereDate('source_period_start', $source->periode_start)
                        ->whereDate('source_period_end', $source->periode_end);
                });
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
