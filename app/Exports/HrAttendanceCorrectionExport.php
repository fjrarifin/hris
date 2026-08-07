<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class HrAttendanceCorrectionExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private Collection $data)
    {
    }

    public function collection(): Collection
    {
        return $this->data->values()->map(function (array $item, int $index): array {
            $correction = $item['correction'] ?? null;
            $correctionType = match ($correction['correction_type'] ?? null) {
                'time' => 'Koreksi Jam Absen',
                'sdc' => 'Sakit Dengan Catatan (SDC)',
                'public_holiday' => 'Libur Nasional (PH)',
                'leave' => 'Cuti',
                'extra_off' => 'Libur Ekstra (EO)',
                default => $correction ? ($correction['correction_type'] ?? 'Koreksi Jam') : '-'
            };

            $hasForm = '-';
            if ($correction) {
                $hasForm = ($correction['has_missing_attendance_form'] ?? false) ? 'Ya' : 'Tidak';
            }

            $correctedAt = '-';
            if ($correction && ! empty($correction['updated_at'])) {
                try {
                    $correctedAt = Carbon::parse($correction['updated_at'])->format('d/m/Y H:i');
                } catch (\Throwable) {
                    $correctedAt = $correction['updated_at'];
                }
            }

            return [
                'no' => $index + 1,
                'date' => Carbon::parse($item['date'])->format('d/m/Y'),
                'nik' => $item['nik'],
                'name' => $item['name'],
                'department' => $item['department'] ?? '-',
                'position' => $item['position'] ?? '-',
                'raw_scan_in' => $item['raw_scan_in'] ?? '-',
                'raw_scan_out' => $item['raw_scan_out'] ?? '-',
                'corrected_scan_in' => $correction['corrected_scan_in'] ?? '-',
                'corrected_scan_out' => $correction['corrected_scan_out'] ?? '-',
                'status_label' => $item['status_label'] ?? '-',
                'correction_type' => $correctionType,
                'has_form' => $hasForm,
                'notes' => $correction['notes'] ?? '-',
                'is_resolved' => $item['is_resolved'] ? 'Sudah Dikoreksi / Disetujui' : 'Perlu Koreksi',
                'corrected_by' => $correction['corrected_by_name'] ?? '-',
                'corrected_at' => $correctedAt,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal Absensi',
            'NIK',
            'Nama Karyawan',
            'Departemen',
            'Jabatan',
            'Scan Masuk (Asli)',
            'Scan Pulang (Asli)',
            'Scan Masuk (Koreksi)',
            'Scan Pulang (Koreksi)',
            'Status Absensi',
            'Jenis Koreksi',
            'Form Lupa Scan',
            'Catatan Koreksi (Notes HRD)',
            'Status Koreksi',
            'Di-Koreksi Oleh',
            'Waktu Koreksi',
        ];
    }
}
