<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class HrOvertimeRecapExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private Collection $data)
    {
    }

    public function collection(): Collection
    {
        return $this->data->map(function ($item): array {
            return [
                'id' => $item['id'],
                'date' => $item['date_formatted'],
                'nik' => $item['employee_nik'],
                'name' => $item['employee_name'],
                'department' => $item['department'],
                'position' => $item['position'],
                'start_time' => $item['start_time'],
                'end_time' => $item['end_time'],
                'duration' => $item['duration_formatted'],
                'duration_hours' => round($item['duration_minutes'] / 60, 2),
                'reason' => $item['reason'],
                'status' => $item['status_label'],
                'hr_approved_at' => $item['hr_approved_at'] ? Carbon::parse($item['hr_approved_at'])->format('d/m/Y H:i') : '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID Pengajuan',
            'Tanggal Lembur',
            'NIK',
            'Nama Karyawan',
            'Departemen',
            'Jabatan / Posisi',
            'Jam Mulai',
            'Jam Selesai',
            'Durasi Lembur',
            'Durasi (Jam)',
            'Pekerjaan / Alasan Lembur',
            'Status Approval',
            'Tanggal Disetujui HRD',
        ];
    }
}
