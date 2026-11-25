<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicHolidayRequest extends Model
{
    protected $fillable = [
        'user_id',
        'public_holiday_id',
        'claim_date',
        'status',
        'manager_approved_at',
        'manager_approved_by',
        'hr_approved_at',
        'hr_approved_by',
        'reject_reason',
        'expired_at',
        'approval_token',
        'approval_token_expires_at',
    ];

    protected $casts = [
        'claim_date' => 'date',
        'expired_at' => 'datetime',
        'manager_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
        'holiday_date' => 'date',
        'approval_token_expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $request): void {
            if (! $request->isDirty(['status', 'manager_approved_at', 'hr_approved_at'])
                || $request->status !== 'approved') {
                return;
            }
            $holiday = $request->holiday;
            if ($holiday && (! \App\Support\PublicHolidayValidity::isValidOn($holiday->holiday_date, $request->claim_date)
                || $request->claim_date->lt($holiday->holiday_date))) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'decision' => 'PH tidak dapat disetujui: tanggal pemakaian di luar masa berlaku 3 bulan.',
                ]);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function holiday()
    {
        return $this->belongsTo(PublicHoliday::class, 'public_holiday_id');
    }
}
