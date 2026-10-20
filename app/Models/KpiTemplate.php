<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiTemplate extends Model
{
    protected $fillable = [
        'nama_template',
        'deskripsi',
        'master_jabatan_id',
        'departemen',
        'created_by',
        'updated_by',
        'creator_nik',
        'status',
        'supervisor_nik',
        'supervisor_approved_at',
        'supervisor_rejected_at',
        'supervisor_notes',
        'hrd_approved_by',
        'hrd_approved_at',
        'hrd_rejected_at',
        'hrd_notes',
        'reject_reason',
        'total_bobot',
        'is_active',
    ];

    protected $casts = [
        'total_bobot' => 'decimal:2',
        'is_active' => 'boolean',
        'supervisor_approved_at' => 'datetime',
        'supervisor_rejected_at' => 'datetime',
        'hrd_approved_at' => 'datetime',
        'hrd_rejected_at' => 'datetime',
    ];

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(MasterJabatan::class, 'master_jabatan_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KpiTemplateItem::class, 'kpi_template_id')->orderBy('urutan')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(KpiTemplateAssignment::class, 'kpi_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creatorEmployee(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'creator_nik', 'nik');
    }

    public function supervisorEmployee(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'supervisor_nik', 'nik');
    }

    public function hrdApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hrd_approved_by');
    }

    public function recalculateTotalBobot(): float
    {
        $total = (float) $this->items()->sum('bobot');
        $this->update(['total_bobot' => $total]);

        return $total;
    }
}
