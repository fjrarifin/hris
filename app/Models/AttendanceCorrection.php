<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model
{
    protected $fillable = [
        'nik',
        'attendance_date',
        'correction_type',
        'corrected_scan_in',
        'corrected_scan_out',
        'has_missing_attendance_form',
        'notes',
        'corrected_by',
        'absence_type',
        'absence_id',
        'leave_accrual_id',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'has_missing_attendance_form' => 'boolean',
    ];
}
