<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePhBalance extends Model
{
    use HasFactory;

    protected $table = 'employee_ph_balances';

    protected $fillable = [
        'karyawan_nik',
        'user_id',
        'public_holiday_id',
        'holiday_date',
        'holiday_name',
        'days',
        'source',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'days'         => 'integer',
        'holiday_date' => 'date',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_nik', 'nik');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
