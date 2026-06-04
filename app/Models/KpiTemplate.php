<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiTemplate extends Model
{
    protected $fillable = ['master_jabatan_id', 'jobdesk_id', 'nama_kpi', 'deskripsi', 'target', 'satuan', 'bobot', 'formula_penilaian', 'is_active', 'created_by', 'updated_by'];

    protected $casts = ['bobot' => 'decimal:2', 'is_active' => 'boolean'];

    public function jabatan()
    {
        return $this->belongsTo(MasterJabatan::class, 'master_jabatan_id');
    }

    public function jobdesk()
    {
        return $this->belongsTo(Jobdesk::class);
    }
}
