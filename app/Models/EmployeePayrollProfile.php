<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePayrollProfile extends Model
{
    protected $fillable = [
        'karyawan_nik',
        'gaji_pokok',
        'tunjangan_jabatan',
        'tunjangan_tidak_tetap',
        'bruto_man_power',
        'payroll_group',
        'dasar_bpjs',
        'dasar_jp',
        'rate_jkk_percent',
        'is_active',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'gaji_pokok' => 'integer',
        'tunjangan_jabatan' => 'integer',
        'tunjangan_tidak_tetap' => 'integer',
        'bruto_man_power' => 'integer',
        'dasar_bpjs' => 'integer',
        'dasar_jp' => 'integer',
        'rate_jkk_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_nik', 'nik');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
