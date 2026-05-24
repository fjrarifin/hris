<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class HrAttendanceExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $records) {}

    public function collection(): Collection
    {
        return $this->records;
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'NIK',
            'Nama Karyawan',
            'Jabatan',
            'Departemen',
            'Unit',
            'Jam Masuk',
            'Jam Keluar',
            'Keterangan',
            'Catatan',
            'Total Kehadiran',
        ];
    }

    public function map($record): array
    {
        return [
            $record['date'],
            $record['nik'],
            $record['name'],
            $record['position'],
            $record['department'],
            $record['unit'],
            $record['scan_in'],
            $record['scan_out'],
            $record['attendance_type'],
            $record['note'],
            $record['attendance_total'],
        ];
    }
}
