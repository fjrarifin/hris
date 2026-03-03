<?php

namespace App\Models;

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

    public function requests()
    {
        return $this->hasMany(PublicHolidayRequest::class);
    }
}
