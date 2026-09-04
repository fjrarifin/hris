<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KaryawanHolding extends Model
{
    use HasFactory;

    protected $table = 'm_karyawan_holding';

    protected $fillable = [
        'nik',
        'nama',
        'jabatan',
        'departemen',
        'perusahaan',
        'no_hp',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function qrTransactions()
    {
        return $this->hasMany(QrHoldingTransaction::class, 'm_karyawan_holding_id');
    }

    public function isHolding(): bool
    {
        return true;
    }

    public function getNamaKaryawanAttribute(): string
    {
        return (string) $this->nama;
    }

    public function getDepartementAttribute(): ?string
    {
        return $this->departemen ?: $this->perusahaan;
    }
}
