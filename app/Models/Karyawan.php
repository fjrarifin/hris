<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Karyawan extends Model
{
    private const ATTENDANCE_RADIUS_EXEMPT_POSITIONS = [
        'manager',
        'gm',
        'general manager',
    ];

    protected $table = 'm_karyawan';

    protected $primaryKey = 'nik';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'pin',
        'nik',
        'nama_karyawan',
        'jabatan',
        'posisi',
        'posisi_level',
        'posisi_title',
        'divisi',
        'departement',
        'unit',
        'nama_atasan_langsung',
        'atasan_tidak_langsung',
        'status_karyawan',
        'join_date',
        'no_hp',
        'phone_updated_at',
        'email',
        'tanggal_lahir',
        'jenis_kelamin',
        'golongan_darah',
        'no_ktp',
        'tempat_lahir',
        'alamat',
        'npwp',
        'no_npwp',
        'status_pajak',
        'status_pernikahan',
        'agama',
        'kewarganegaraan',
        'pendidikan_terakhir',
        'nama_institusi',
        'jurusan',
        'nama_pasangan',
        'jumlah_anak',
        'children',
        'nama_anak_1',
        'nama_anak_2',
        'nama_anak_3',
        'nama_ayah',
        'nama_ibu',
        'kontak_darurat_nama',
        'kontak_darurat_hubungan',
        'kontak_darurat_no_hp',
        'bank',
        'no_rekening',
        'bpjs',
        'no_bpjs',
    ];

    protected $casts = [
        'join_date' => 'date',
        'tanggal_lahir' => 'date',
        'phone_updated_at' => 'datetime',
        'bpjs' => 'boolean',
        'npwp' => 'boolean',
        'jumlah_anak' => 'integer',
        'children' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'nik', 'username');
    }

    public function dailySchedules()
    {
        return $this->hasMany(EmployeeDailySchedule::class, 'karyawan_nik', 'nik');
    }

    public function payrollProfile()
    {
        return $this->hasOne(EmployeePayrollProfile::class, 'karyawan_nik', 'nik');
    }

    public function requiresAttendanceRadius(): bool
    {
        $positionTitle = strtolower(trim((string) ($this->posisi_title ?: $this->jabatan ?: $this->posisi)));

        return ! in_array($positionTitle, self::ATTENDANCE_RADIUS_EXEMPT_POSITIONS, true);
    }
}
