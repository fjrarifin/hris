<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class HrAttendanceExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Collection $records,
        private readonly Collection $dates
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
            ...$this->dates->map(fn (string $date) => Carbon::parse($date)->format('d/m/Y'))->all(),
            'Total Durasi Jam Kerja',
            'Total M',
            'Total A',
            'Total PH',
            'Total C',
            'Total S',
            'Total I',
            'Total M Hari Libur Nasional',
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
            ...$this->dates->map(fn (string $date) => $this->dayLabel($record['days'][$date]))->all(),
            $record['total_work_duration'],
            $record['total_present'],
            $record['total_alpha'],
            $record['total_ph'],
            $record['total_leave'],
            $record['total_sick'],
            $record['total_permission'],
            $record['total_national_holiday_attendance'],
        ];
    }

    private function dayLabel(array $day): string
    {
        return $day['status'];
    }
}
