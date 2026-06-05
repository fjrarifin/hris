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
        'basic_salary',
        'bruto_man_power',
        'total_hari_masuk',
        'extra_off_days',
        'tunjangan_tidak_tetap_full',
        'formula_version',
        'approval_status',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'approval_notes',
        'is_locked',
        'locked_by',
        'locked_at',
        'validation_status',
        'validation_warnings',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'periode_start' => 'date',
        'periode_end' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'validation_warnings' => 'array',
        'validated_at' => 'datetime',
        'basic_salary' => 'integer',
        'bruto_man_power' => 'integer',
        'total_hari_masuk' => 'integer',
        'extra_off_days' => 'integer',
        'tunjangan_tidak_tetap_full' => 'integer',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_nik', 'nik');
    }

    public function items()
    {
        return $this->hasMany(PayrollItem::class, 'payroll_id');
    }

    public function getFormattedItemsAttribute()
    {
        $items = $this->items;
        if (!$items) {
            return collect();
        }

        $penyesuaian = $items->firstWhere('nama_item', 'Penyesuaian Pembulatan') ?? $items->firstWhere('component.nama', 'Penyesuaian Pembulatan');
        $potongan = $items->firstWhere('nama_item', 'Potongan Pembulatan') ?? $items->firstWhere('component.nama', 'Potongan Pembulatan');
        
        if (!$penyesuaian && !$potongan) {
            return $items;
        }

        $penyesuaianAmount = $penyesuaian ? $penyesuaian->amount : 0;
        $potonganAmount = $potongan ? $potongan->amount : 0;

        return $items->map(function ($item) use ($penyesuaianAmount, $potonganAmount) {
            $name = $item->component?->nama ?? $item->nama_item;
            if ($name === 'Tunjangan Tidak Tetap') {
                $newItem = clone $item;
                $newItem->amount = $item->amount + $penyesuaianAmount - $potonganAmount;
                return $newItem;
            }
            return $item;
        })->filter(function ($item) {
            $name = $item->component?->nama ?? $item->nama_item;
            return !in_array($name, ['Penyesuaian Pembulatan', 'Potongan Pembulatan']);
        })->values();
    }

    public function getTotalPendapatanAttribute($value)
    {
        if (!$this->relationLoaded('items')) return $value;
        $potongan = $this->items->firstWhere('nama_item', 'Potongan Pembulatan') ?? $this->items->firstWhere('component.nama', 'Potongan Pembulatan');
        if ($potongan) {
            return $value - $potongan->amount;
        }
        return $value;
    }

    public function getTotalPotonganAttribute($value)
    {
        if (!$this->relationLoaded('items')) return $value;
        $potongan = $this->items->firstWhere('nama_item', 'Potongan Pembulatan') ?? $this->items->firstWhere('component.nama', 'Potongan Pembulatan');
        if ($potongan) {
            return $value - $potongan->amount;
        }
        return $value;
    }

    public function emailLogs()
    {
        return $this->hasMany(PayrollEmailLog::class);
    }

    public function latestEmailLog()
    {
        return $this->hasOne(PayrollEmailLog::class)->latestOfMany();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function locker()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function earnings()
    {
        return $this->items()->where('type', 'earning');
    }

    public function deductions()
    {
        return $this->items()->where('type', 'deduction');
    }

    public function employerContributions()
    {
        return $this->items()->where('type', 'employer_contribution');
    }

    private function normalizeComponentName($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $value));
    }

    public function getItemByComponentName($componentName, $type = null)
    {
        if ($componentName === null || $componentName === '') {
            return null;
        }

        $target = $this->normalizeComponentName($componentName);

        return $this->formatted_items->first(function ($item) use ($target, $type) {
            if ($type !== null && $item->type !== $type) {
                return false;
            }

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
