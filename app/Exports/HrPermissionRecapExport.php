<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class HrPermissionRecapExport extends StringValueBinder implements FromCollection, ShouldAutoSize, WithHeadings, WithCustomValueBinder
{
    public function __construct(private Collection $records) {}

    public function collection(): Collection
    {
        return $this->records->map(fn (array $item) => [
            $item['id'], $item['employee_nik'], $item['employee_name'], $item['department'],
            ucfirst($item['type']), $item['start_date'], $item['end_date'], $item['days'],
            $item['reason'] ?? '-', $item['has_document'] ? 'Ada' : 'Tidak ada',
            $item['status_label'], $item['reject_reason'] ?? '-',
        ]);
    }

    public function headings(): array
    {
        return ['ID', 'NIK', 'Nama Karyawan', 'Departemen', 'Jenis', 'Tanggal Mulai', 'Tanggal Selesai', 'Hari dalam Periode', 'Alasan', 'Dokumen Pendukung', 'Status', 'Alasan Penolakan'];
    }
}
