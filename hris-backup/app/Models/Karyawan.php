<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Karyawan extends Model
{
    protected $table = 'm_karyawan';
    protected $primaryKey = 'nik';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'nik',
        'nama_karyawan',
        'jabatan',
        'posisi',
        'divisi',
        'departement',
        'unit',
        'nama_atasan_langsung',
        'status_kontrak',
        'join_date',
        'start_date',
        'durasi_kontrak',
        'end_date',
        'total_masa_kerja',
        'no_hp',
        'jenis_kelamin',
    ];

    protected $casts = [
        'join_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
