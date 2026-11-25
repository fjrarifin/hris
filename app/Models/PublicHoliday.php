<?php

namespace App\Models;

use App\Support\PublicHolidayValidity;
use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    protected $fillable = [
        'name',
        'holiday_date',
        'year',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'expired_at',
    ];

    public function getExpiredAtAttribute(): ?string
    {
        return $this->holiday_date
            ? PublicHolidayValidity::expiresAt($this->holiday_date)->toDateString()
            : null;
    }

    public function requests()
    {
        return $this->hasMany(PublicHolidayRequest::class);
    }
}
