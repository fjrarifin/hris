<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FingerspotAttendanceLog extends Model
{
    protected $fillable = [
        'pin',
        'scan_date',
        'verify',
        'status_scan',
        'trans_id',
        'cloud_id',
        'source',
        'raw_payload',
    ];

    protected $casts = [
        'scan_date' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'pin', 'nik');
    }
}
