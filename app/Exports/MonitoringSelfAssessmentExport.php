<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MonitoringSelfAssessmentExport implements FromCollection, WithHeadings
{
    protected $periode;

    public function __construct($periode)
    {
        $this->periode = $periode;
    }

    public function collection()
    {
        return DB::table('m_karyawan as k')
            ->leftJoin('t_penilaian_self as s', function ($join) {
                $join->on(DB::raw('s.nik COLLATE utf8mb4_unicode_ci'), '=', DB::raw('k.nik COLLATE utf8mb4_unicode_ci'))
                    ->where('s.periode', $this->periode);
            })
            ->select([
                'k.nik',
                'k.nama_karyawan',
                'k.jabatan',
                DB::raw("CASE WHEN s.id IS NULL THEN 'Belum Submit' ELSE 'Sudah Submit' END as status"),
                's.kesulitan',
                's.improvement',
                's.perbaikan_hompimplay',
                's.catatan_rekan',
                's.submitted_at',
            ])
            ->orderBy('k.nama_karyawan')
            ->get();
    }

    public function headings(): array
    {
        return [
            'NIK',
            'Nama',
            'Jabatan',
            'Status',
            'Kesulitan',
            'Improvement',
            'Perbaikan Hompimplay',
            'Catatan Rekan',
            'Waktu Submit',
        ];
    }
}