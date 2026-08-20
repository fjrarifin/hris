<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitorLog extends Model
{
    use HasFactory;

    protected $table = 't_visitor_logs';

    protected $fillable = [
        'nomor_kunjungan',
        'nomor_identitas',
        'nama_visitor',
        'no_hp',
        'instansi',
        'tujuan_bertemu',
        'keperluan',
        'ip_address',
        'user_agent',
        'waktu_masuk',
    ];

    protected $casts = [
        'waktu_masuk' => 'datetime',
    ];
}
