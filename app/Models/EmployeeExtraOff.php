<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeExtraOff extends Model
{
    protected $fillable = [
        'karyawan_nik',
        'periode_start',
        'periode_end',
        'days',
        'source',
        'notes',
        'expires_at',
        'public_holiday_request_id',
    ];

    protected $casts = [
        'periode_start' => 'date',
        'periode_end' => 'date',
        'days' => 'integer',
        'expires_at' => 'date',
    ];

    public function expirationDate(): \Carbon\Carbon
    {
        return ($this->expires_at?->copy() ?? $this->periode_end->copy()->addMonths(3))->endOfDay();
    }

    public function matchesRequest(ExtraOffRequest $request): bool
    {
        if ($request->employee_extra_off_id) {
            return (int) $request->employee_extra_off_id === (int) $this->id;
        }

        return ! $this->public_holiday_request_id
            && $request->source_period_start?->equalTo($this->periode_start)
            && $request->source_period_end?->equalTo($this->periode_end);
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_nik', 'nik');
    }
}
