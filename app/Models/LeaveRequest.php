<?php

namespace App\Models;

use App\Models\User;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected $fillable = [
        'user_id',
        'leave_type',
        'start_date',
        'end_date',
        'reason',
        'status',
        'reject_reason',
        'manager_approved_at',
        'hr_approved_at',
        'manager_approved_by',
        'hr_approved_by',
        'second_manager_approved_at',
        'second_manager_approved_by',
        'approval_token',
        'approval_token_expires_at',
    ];

    public const LEAVE_TYPES = [
        'cuti_tahunan' => 'Cuti Tahunan',
        'lainnya' => 'Cuti Lainnya',
    ];

    public static function whatsappTemplates(): array
    {
        return [
            'cuti_tahunan' => fn($leave, $karyawan) =>
            "📌 *Pengajuan Cuti*\n\n" .
                "Nama: {$karyawan->nama_karyawan}\n" .
                "Periode: {$leave->start_date} s/d {$leave->end_date}\n\n" .
                "Jenis Cuti: Tahunan\n" .
                "Status: *Menunggu Persetujuan*",

            'cuti_hamil_melahirkan' => fn($leave, $karyawan) =>
            "🤰 *Pengajuan Cuti*\n\n" .
                "Nama: {$karyawan->nama_karyawan}\n" .
                "Jenis Cuti: Hamil & Melahirkan\n" .
                "Perkiraan Periode: {$leave->start_date} s/d {$leave->end_date}",

            'cuti_menikah' => fn($leave, $karyawan) =>
            "💍 *Pengajuan Cuti*\n\n" .
                "Nama: {$karyawan->nama_karyawan}\n" .
                "Jenis Cuti: Menikah\n" .
                "Tanggal: {$leave->start_date}",

            'public_holiday' => fn($leave, $karyawan) =>
            "📅 *Pengajuan Hari Libur*\n\n" .
                "Nama: {$karyawan->nama_karyawan}\n" .
                "Tanggal: {$leave->start_date}",

            'lainnya' => fn($leave, $karyawan) =>
            "📄 *Pengajuan Cuti*\n\n" .
                "Nama: {$karyawan->nama_karyawan}\n" .
                "Periode: {$leave->start_date} s/d {$leave->end_date}\n" .
                "Keterangan: {$leave->reason}",
        ];
    }
}
