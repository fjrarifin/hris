<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiTemplateItem extends Model
{
    protected $table = 'kpi_template_items';

    protected $fillable = [
        'kpi_template_id',
        'master_jabatan_id',
        'nama_kpi',
        'deskripsi',
        'target',
        'satuan',
        'bobot',
        'formula_penilaian',
        'urutan',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'bobot' => 'decimal:2',
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class, 'kpi_template_id');
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(MasterJabatan::class, 'master_jabatan_id');
    }
}
