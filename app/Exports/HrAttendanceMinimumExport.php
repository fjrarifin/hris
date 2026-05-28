<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class HrAttendanceMinimumExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Collection $records,
        private readonly array $targets
    ) {}

    public function collection(): Collection
    {
        return $this->records;
    }

    public function headings(): array
    {
        return [
            'NIK',
            'Nama Karyawan',
            'Jabatan',
            'Departemen',
            'Unit',
            'Status Karyawan',
            'Target Hari',
            'Total Hari',
            'Selisih Hari',
            'Target Durasi',
            'Durasi Kerja',
            'Selisih Durasi',
            'Total Lembur',
            'Status',
        ];
    }

    public function map($record): array
    {
        return [
            $record['nik'],
            $record['name'],
            $record['position'],
            $record['department'],
            $record['unit'],
            $record['employee_status'],
            $this->targets['ideal_attendance_days'],
            $record['total_attendance'],
            $record['attendance_diff_label'],
            $this->targets['minimum_work_duration'],
            $record['total_work_duration'],
            $record['work_duration_diff'],
            $record['total_overtime'],
            $record['status_label'],
        ];
    }
}
