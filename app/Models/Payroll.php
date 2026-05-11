<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    protected $fillable = [
        'karyawan_nik',
        'periode_start',
        'periode_end',
        'hari_kerja',
        'hadir',
        'libur',
        'total_pendapatan',
        'total_potongan',
        'total_dibayarkan',
        'izin',
        'sakit_surat',
        'sakit_tanpa_surat',
        'tanpa_keterangan',
        'cuti_tahunan',
        'cuti_normatif',
        'libur_nasional',
        'ph',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_nik', 'nik');
    }

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function earnings()
    {
        return $this->items()->where('type', 'earning');
    }

    public function deductions()
    {
        return $this->items()->where('type', 'deduction');
    }

    private function normalizeComponentName($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return strtolower(preg_replace('/[^a-z0-9]/', '', (string) $value));
    }

    public function getItemByComponentName($componentName)
    {
        if ($componentName === null || $componentName === '') {
            return null;
        }

        $target = $this->normalizeComponentName($componentName);

        return $this->items->first(function ($item) use ($target) {
            if ($item->component) {
                $normalized = $this->normalizeComponentName($item->component->nama ?? '');
                if ($normalized === $target) {
                    return true;
                }
            }

            $itemNorm = $this->normalizeComponentName($item->nama_item ?? '');
            return $itemNorm === $target;
        });
    }
}
