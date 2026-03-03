<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenilaianHdr extends Model
{
    protected $table = 't_penilaian_hdr';

    protected $fillable = [
        'nik_penilai',
        'tanggal',
        'periode',
        'total_relasi',
    ];

    public function details()
    {
        return $this->hasMany(PenilaianDtl::class, 'penilaian_id');
    }
}
