<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrHoldingTransaction extends Model
{
    use HasFactory;

    protected $table = 't_qr_holding';

    protected $fillable = [
        'm_karyawan_holding_id',
        'nik',
        'nama',
        'perusahaan',
        'qr_payload',
        'access_date_code',
        'ip_address',
        'user_agent',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function karyawanHolding()
    {
        return $this->belongsTo(KaryawanHolding::class, 'm_karyawan_holding_id');
    }
}
