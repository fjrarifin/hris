<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FingerspotAttendanceLogExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $logs)
    {
    }

    public function collection(): Collection
    {
        return $this->logs;
    }

    public function headings(): array
    {
        return [
            'Cloud ID',
            'ID',
            'Nama',
            'Tanggal Absensi',
            'Jam Absensi',
            'Verifikasi',
            'Tipe Absensi',
            'Jabatan',
        ];
    }

    public function map($log): array
    {
        return [
            $log->cloud_id,
            $log->pin,
            $log->karyawan?->nama_karyawan,
            optional($log->scan_date)->format('Y-m-d'),
            optional($log->scan_date)->format('H:i:s'),
            $log->verify,
            $this->attendanceType($log->status_scan),
            $log->karyawan?->unit,
        ];
    }

    private function attendanceType($value): string
    {
        return match ((string) $value) {
            '0' => 'Absen Masuk',
            '1' => 'Absen Keluar',
            default => (string) ($value ?? '-'),
        };
    }
}
