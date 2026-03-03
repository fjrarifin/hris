<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtkRequest extends Model
{
    protected $table = 't_atk_request';

    protected $fillable = [
        'request_no',
        'nik',
        'nama_karyawan',
        'jabatan',
        'divisi',
        'tanggal_pengajuan',
        'nama_barang',
        'qty',
        'satuan',
        'keterangan',
        'status',
        'approved_by',
        'approved_at',
        'catatan_admin',
    ];
}
