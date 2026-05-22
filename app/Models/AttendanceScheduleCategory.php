<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceScheduleCategory extends Model
{
    protected $fillable = [
        'code',
        'name',
        'start_time',
        'end_time',
        'type',
        'is_workday',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_workday' => 'boolean',
        'is_active' => 'boolean',
    ];
}
