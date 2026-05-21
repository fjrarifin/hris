<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Karyawan extends Model
{
    protected $table = 'm_karyawan';

    protected $primaryKey = 'nik';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
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
        'status_kontrak',
        'join_date',
        'start_date',
        'durasi_kontrak',
        'end_date',
        'total_masa_kerja',
        'no_hp',
        'phone_updated_at',
        'email',
        'tanggal_lahir',
        'jenis_kelamin',
        'no_ktp',
        'tempat_lahir',
        'alamat',
        'npwp',
        'no_npwp',
        'status_pernikahan',
        'agama',
        'kewarganegaraan',
        'pendidikan_terakhir',
        'nama_institusi',
        'jurusan',
        'nama_pasangan',
        'jumlah_anak',
        'nama_ayah',
        'nama_ibu',
        'kontak_darurat_nama',
        'kontak_darurat_hubungan',
        'kontak_darurat_no_hp',
        'account_name',
        'bank',
        'no_rekening',
        'bpjs',
        'no_bpjs',
    ];

    protected $casts = [
        'join_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'tanggal_lahir' => 'date',
        'phone_updated_at' => 'datetime',
        'bpjs' => 'boolean',
        'npwp' => 'boolean',
        'jumlah_anak' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'nik', 'username');
    }
}
