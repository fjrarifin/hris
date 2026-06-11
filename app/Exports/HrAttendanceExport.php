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
        private readonly string $format = 'detail'
    ) {}

    public function collection(): Collection
    {
        return $this->records;
    }

    public function headings(): array
    {
        $dailyHeadings = match ($this->format) {
            'full' => $this->dates
                ->flatMap(fn (string $date) => [
                    Carbon::parse($date)->format('d/m/Y').' Status',
                    Carbon::parse($date)->format('d/m/Y').' Masuk',
                    Carbon::parse($date)->format('d/m/Y').' Pulang',
                    Carbon::parse($date)->format('d/m/Y').' Durasi',
                    Carbon::parse($date)->format('d/m/Y').' Keterangan',
                ])
                ->all(),
            'detail' => $this->dates->map(fn (string $date) => Carbon::parse($date)->format('d/m/Y'))->all(),
            default => [],
        };

        return [
            'NIK',
            'Nama Karyawan',
            'Jabatan',
            'Departemen',
            'Unit',
            ...$dailyHeadings,
            'Total Hari Periode',
            'Total Kehadiran',
            'Total Durasi Jam Kerja',
            'Total Lembur',
            'Total M',
            'Total A',
            'Total PH',
            'Total EO',
            'Total C',
            'Total Sakit Dengan Surat',
            'Total Sakit Tanpa Surat',
            'Total I',
            'Total M Hari Libur Nasional',
            'Total A Hari Libur Nasional',
        ];
    }

    public function map($record): array
    {
        $dailyValues = match ($this->format) {
            'full' => $this->dates
                ->flatMap(fn (string $date) => $this->fullDayColumns($record['days'][$date]))
                ->all(),
            'detail' => $this->dates->map(fn (string $date) => $this->dayLabel($record['days'][$date]))->all(),
            default => [],
        };

        return [
            $record['nik'],
            $record['name'],
            $record['position'],
            $record['department'],
            $record['unit'],
            ...$dailyValues,
            $record['total_period_days'],
            $record['total_attendance'],
            $record['total_work_duration'],
            $record['total_overtime'],
            $record['total_present'],
            $record['total_alpha'],
            $record['total_ph'],
            $record['total_eo'] ?? 0,
            $record['total_leave'],
            $record['total_sick_with_document'] ?? 0,
            $record['total_sick_without_document'] ?? 0,
            $record['total_permission'],
            $record['total_national_holiday_attendance'],
            $record['total_national_holiday_alpha'],
        ];
    }

    private function dayLabel(array $day): string
    {
        return $day['status'];
    }

    private function fullDayColumns(array $day): array
    {
        return [
            $this->dayLabel($day),
            $this->timeLabel($day['scan_in'] ?? null),
            $this->timeLabel($day['scan_out'] ?? null),
            $day['duration_label'] ?? '',
            $this->dayNote($day),
        ];
    }

    private function timeLabel(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '-';
    }

    private function dayNote(array $day): string
    {
        $notes = [];

        if (($day['status'] ?? null) === 'S') {
            $notes[] = ($day['has_document'] ?? false) ? 'Sakit dengan surat' : 'Sakit tanpa surat';
        }

        if (($day['has_incomplete_scan'] ?? false)) {
            $notes[] = 'Scan tidak lengkap';
        }

        if (($day['is_under_daily_target'] ?? false)) {
            $notes[] = 'Durasi kurang dari 8 jam';
        }

        if (! empty($day['holiday_name'])) {
            $notes[] = 'Libur nasional: '.$day['holiday_name'];
        }

        if (! empty($day['note'])) {
            $notes[] = $day['note'];
        }

        return implode('; ', $notes);
    }
}
