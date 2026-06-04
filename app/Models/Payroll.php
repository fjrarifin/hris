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
        return $this->hasMany(PayrollItem::class);
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
