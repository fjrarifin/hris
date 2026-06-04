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
    ];

    protected $casts = [
        'periode_start' => 'date',
        'periode_end' => 'date',
        'days' => 'integer',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_nik', 'nik');
    }
}
