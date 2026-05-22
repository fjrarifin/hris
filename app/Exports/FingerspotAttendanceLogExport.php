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
            $this->verificationType($log->verify),
            $this->attendanceType($log->status_scan),
            $log->karyawan?->jabatan,
        ];
    }

    private function verificationType($value): string
    {
        return match ((string) $value) {
            '1' => 'Finger',
            '2' => 'Password',
            '3' => 'Card',
            '4' => 'Face',
            '6' => 'Vein',
            '7' => 'QR',
            default => (string) ($value ?? '-'),
        };
    }

    private function attendanceType($value): string
    {
        return match ((string) $value) {
            '0' => 'Scan In',
            '1' => 'Scan Out',
            '2' => 'Break In',
            '3' => 'Break Out',
            '4' => 'Overtime In',
            '5' => 'Overtime Out',
            '6' => 'Rapat In',
            '7' => 'Rapat Out',
            '8' => 'Custom 1',
            '9' => 'Custom 2',
            default => (string) ($value ?? '-'),
        };
    }
}
