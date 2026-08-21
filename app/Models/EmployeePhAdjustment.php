<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePhAdjustment extends Model
{
    use HasFactory;

    protected $table = 'employee_ph_adjustments';

    protected $fillable = [
        'user_id',
        'karyawan_nik',
        'public_holiday_id',
        'days',
        'adjustment_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'days' => 'integer',
        'adjustment_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_nik', 'nik');
    }

    public function holiday(): BelongsTo
    {
        return $this->belongsTo(PublicHoliday::class, 'public_holiday_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
