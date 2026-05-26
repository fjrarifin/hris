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
        private readonly Collection $dates,
        private readonly bool $withDailyBreakdown = true
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
            ...($this->withDailyBreakdown
                ? $this->dates->map(fn (string $date) => Carbon::parse($date)->format('d/m/Y'))->all()
                : []),
            'Total Hari Periode',
            'Total Kehadiran',
            'Total Durasi Jam Kerja',
            'Total Lembur',
            'Total M',
            'Total A',
            'Total PH',
            'Total C',
            'Total S',
            'Total I',
            'Total M Hari Libur Nasional',
            'Total A Hari Libur Nasional',
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
            ...($this->withDailyBreakdown
                ? $this->dates->map(fn (string $date) => $this->dayLabel($record['days'][$date]))->all()
                : []),
            $record['total_period_days'],
            $record['total_attendance'],
            $record['total_work_duration'],
            $record['total_overtime'],
            $record['total_present'],
            $record['total_alpha'],
            $record['total_ph'],
            $record['total_leave'],
            $record['total_sick'],
            $record['total_permission'],
            $record['total_national_holiday_attendance'],
            $record['total_national_holiday_alpha'],
        ];
    }

    private function dayLabel(array $day): string
    {
        return $day['status'];
    }
}
