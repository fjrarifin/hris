<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenilaianDtl extends Model
{
    protected $table = 't_penilaian_dtl';

    protected $fillable = [
        'penilaian_id',
        'nik_relasi',
        'faktor_id',
        'nilai',
        'catatan',
    ];

    public function header()
    {
        return $this->belongsTo(PenilaianHdr::class, 'penilaian_id');
    }
}
