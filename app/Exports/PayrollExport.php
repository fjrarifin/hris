<?php

namespace App\Exports;

use App\Models\Payroll;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PayrollExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private readonly array $filters = [])
    {
    }

    public function collection(): Collection
    {
        return Payroll::with(['karyawan', 'items.component', 'latestEmailLog'])
            ->when($this->filters['periode_start'] ?? null, function ($query, $date) {
                $query->whereDate('periode_start', $date);
            })
            ->when($this->filters['periode_end'] ?? null, function ($query, $date) {
                $query->whereDate('periode_end', $date);
            })
            ->when($this->filters['approval_status'] ?? null, function ($query, $status) {
                $query->where('approval_status', $status);
            })
            ->orderByDesc('periode_start')
            ->orderBy('karyawan_nik')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Periode Awal',
            'Periode Akhir',
            'NIK',
            'Nama Karyawan',
            'Departemen',
            'Unit',
            'Jabatan',
            'Hadir',
            'Libur',
            'Izin',
            'Sakit Surat',
            'Sakit Tanpa Surat',
            'PH',
            'Total Pendapatan',
            'Total Potongan',
            'Total Dibayarkan',
            'Approval',
            'Locked',
            'Validasi',
            'Warning',
            'Email Terakhir',
        ];
    }

    public function map($payroll): array
    {
        $warnings = $payroll->validation_warnings ?? [];
        $warningText = collect($warnings['critical'] ?? [])
            ->merge($warnings['warnings'] ?? [])
            ->implode(' | ');

        return [
            optional($payroll->periode_start)->format('Y-m-d'),
            optional($payroll->periode_end)->format('Y-m-d'),
            $payroll->karyawan_nik,
            $payroll->karyawan?->nama_karyawan,
            $payroll->karyawan?->departement,
            $payroll->karyawan?->unit,
            $payroll->karyawan?->jabatan,
            $payroll->hadir,
            $payroll->libur,
            $payroll->izin,
            $payroll->sakit_surat,
            $payroll->sakit_tanpa_surat,
            $payroll->ph,
            $payroll->total_pendapatan,
            $payroll->total_potongan,
            $payroll->total_dibayarkan,
            $payroll->approval_status,
            $payroll->is_locked ? 'Ya' : 'Tidak',
            $payroll->validation_status,
            $warningText,
            $payroll->latestEmailLog
                ? $payroll->latestEmailLog->status . ' - ' . optional($payroll->latestEmailLog->created_at)->format('Y-m-d H:i')
                : '-',
        ];
    }
}
