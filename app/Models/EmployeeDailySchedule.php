<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDailySchedule extends Model
{
    protected $fillable = [
        'karyawan_nik',
        'schedule_date',
        'schedule_category_id',
        'schedule_code',
        'source',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'schedule_date' => 'date',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_nik', 'nik');
    }

    public function category()
    {
        return $this->belongsTo(AttendanceScheduleCategory::class, 'schedule_category_id');
    }
}
