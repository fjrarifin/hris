<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MonitoringSelfAssessmentExport;

class MonitoringSelfAssessmentController extends Controller
{
    public function index(Request $request)
    {
        $periode = $request->get('periode', now()->format('Y-m'));

        // TOTAL KARYAWAN
        $totalKaryawan = DB::table('m_karyawan')->count();

        // SUDAH SUBMIT
        $sudahSubmit = DB::table('t_penilaian_self')
            ->where('periode', $periode)
            ->distinct()
            ->count('nik');

        $belumSubmit = $totalKaryawan - $sudahSubmit;

        // LISTING UTAMA
        $rows = DB::table('m_karyawan as k')
            ->leftJoin('t_penilaian_self as s', function ($join) use ($periode) {
                $join->on(DB::raw('s.nik COLLATE utf8mb4_unicode_ci'), '=', DB::raw('k.nik COLLATE utf8mb4_unicode_ci'))
                    ->where('s.periode', $periode);
            })
            ->select([
                'k.nik',
                'k.nama_karyawan',
                'k.jabatan',
                'k.posisi',
                's.submitted_at',
                DB::raw("CASE WHEN s.id IS NULL THEN 'belum' ELSE 'sudah' END as status"),
            ])
            ->orderByRaw("s.submitted_at IS NULL DESC")
            ->orderBy('k.nama_karyawan')
            ->get();

        return view('hr.monitoring.sa', [
            'periode'        => $periode,
            'rows'           => $rows,
            'totalKaryawan'  => $totalKaryawan,
            'sudahSubmit'    => $sudahSubmit,
            'belumSubmit'    => $belumSubmit,
        ]);
    }

    public function detail($nik, Request $request)
    {
        $periode = $request->get('periode', now()->format('Y-m'));

        $data = DB::table('t_penilaian_self as s')
            ->join('m_karyawan as k', function ($join) {
                $join->on(DB::raw('k.nik COLLATE utf8mb4_unicode_ci'), '=', DB::raw('s.nik COLLATE utf8mb4_unicode_ci'));
            })
            ->where('s.nik', $nik)
            ->where('s.periode', $periode)
            ->select([
                'k.nik',
                'k.nama_karyawan',
                'k.jabatan',
                's.kesulitan',
                's.improvement',
                's.perbaikan_hompimplay',
                's.catatan_rekan',
                's.submitted_at',
            ])
            ->first();

        return response()->json($data);
    }

    public function export(Request $request)
    {
        $periode = $request->get('periode', now()->format('Y-m'));

        return Excel::download(
            new MonitoringSelfAssessmentExport($periode),
            'Monitoring_Self_Assessment_' . $periode . '.xlsx'
        );
    }
}
