<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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
        'rfid_tag',
        'nik',
        'nama_karyawan',
        'jabatan',
        'posisi',
        'posisi_level',
        'posisi_title',
        'bisnis_unit',
        'divisi',
        'departement',
        'unit',
        'nama_atasan_langsung',
        'atasan_tidak_langsung',
        'atasan_langsung_nik',
        'atasan_tidak_langsung_nik',
        'status_karyawan',
        'join_date',
        'end_date',
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
        'nama_pemilik_rekening',
        'bpjs',
        'no_bpjs',
    ];

    protected $casts = [
        'join_date' => 'date',
        'end_date' => 'date',
        'tanggal_lahir' => 'date',
        'phone_updated_at' => 'datetime',
        'bpjs' => 'boolean',
        'npwp' => 'boolean',
        'jumlah_anak' => 'integer',
        'children' => 'array',
    ];

    protected $appends = [
        'nama_atasan_langsung',
        'atasan_tidak_langsung',
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

    public function atasanLangsung()
    {
        return $this->belongsTo(Karyawan::class, 'atasan_langsung_nik', 'nik');
    }

    public function atasanTidakLangsung()
    {
        return $this->belongsTo(Karyawan::class, 'atasan_tidak_langsung_nik', 'nik');
    }

    public function getNamaAtasanLangsungAttribute(): ?string
    {
        return $this->atasanLangsung?->nama_karyawan;
    }

    public function getAtasanTidakLangsungAttribute(): ?string
    {
        return $this->getRelationValue('atasanTidakLangsung')?->nama_karyawan;
    }

    public function kpiAssignments()
    {
        return $this->hasMany(KpiTemplateAssignment::class, 'employee_nik', 'nik');
    }

    public function isHolding(): bool
    {
        return false;
    }

    /**
     * Ambil daftar bawahan aktif (langsung & tidak langsung) milik seorang atasan.
     */
    public static function activeSubordinatesFor(string $supervisorNik): Collection
    {
        if ($supervisorNik === '' || ! Schema::hasTable('m_karyawan')) {
            return collect();
        }

        $hasDirectCol = Schema::hasColumn('m_karyawan', 'atasan_langsung_nik');
        $hasIndirectCol = Schema::hasColumn('m_karyawan', 'atasan_tidak_langsung_nik');
        if (! $hasDirectCol && ! $hasIndirectCol) {
            return collect();
        }

        $selectCols = array_values(array_filter([
            'nik',
            'nama_karyawan',
            'jabatan',
            'status_karyawan',
            $hasDirectCol ? 'atasan_langsung_nik' : null,
            $hasIndirectCol ? 'atasan_tidak_langsung_nik' : null,
        ]));

        return static::query()
            ->where('nik', '!=', $supervisorNik)
            ->where(function ($q) use ($supervisorNik, $hasDirectCol, $hasIndirectCol): void {
                if ($hasDirectCol) {
                    $q->where('atasan_langsung_nik', $supervisorNik);
                }
                if ($hasIndirectCol) {
                    $q->orWhere('atasan_tidak_langsung_nik', $supervisorNik);
                }
            })
            ->where(function ($q): void {
                $q->whereNull('status_karyawan')
                    ->orWhereRaw("UPPER(TRIM(status_karyawan)) NOT IN ('RESIGN', 'NONAKTIF', 'NON-AKTIF')");
            })
            ->orderBy('nama_karyawan')
            ->get($selectCols);
    }

    /**
     * Cek apakah karyawan menjadi supervisor lowongan pada modul Kandidat Bawahan.
     */
    public static function hasVacancySupervisionFor(string $supervisorNik): bool
    {
        if (
            $supervisorNik === ''
            || ! Schema::hasTable('recruitment_vacancies')
            || ! Schema::hasColumn('recruitment_vacancies', 'supervisor_nik')
        ) {
            return false;
        }

        return RecruitmentVacancy::query()
            ->where('supervisor_nik', $supervisorNik)
            ->exists();
    }
}
