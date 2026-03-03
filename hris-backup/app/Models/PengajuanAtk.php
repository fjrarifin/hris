<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanAtk extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_atk';

    protected $fillable = [
        'request_no',
        'nik',
        'nama_barang',
        'qty',
        'satuan',
        'keterangan',
        'tanggal_pengajuan',
        'status',
        'approved_by',
        'approved_at',
        'rejected_reason',
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'date',
        'approved_at' => 'datetime',
    ];

    // Relasi ke tabel m_karyawan
    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'nik', 'nik');
    }

    // Generate nomor request otomatis
    public static function generateRequestNo()
    {
        $year = date('Y');
        $lastRequest = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastRequest) {
            $lastNumber = (int) substr($lastRequest->request_no, -3);
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }

        return 'ATK-' . $year . '-' . $newNumber;
    }
}
