<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Monitoring360Controller extends Controller
{
    public function index(Request $request)
    {
        $periode = $request->get('periode', now()->format('Y-m'));
        $kategori = $request->get('kategori');
        $search = trim($request->get('q', ''));
        $status = $request->get('status');

        // ✅ HITUNG TOTAL YANG SEHARUSNYA SUBMIT (dari m_relation - unique nik_penilai)
        $totalExpectedPenilai = DB::table('m_relation')
            ->distinct()
            ->count('nik');

        // ✅ HITUNG TOTAL YANG SUDAH SUBMIT di periode ini
        $totalSubmit = DB::table('t_penilaian_hdr')
            ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periode])
            ->distinct()
            ->count('nik_penilai');

        // ✅ HITUNG TOTAL YANG BELUM SUBMIT
        $totalBelumSubmit = $totalExpectedPenilai - $totalSubmit;

        // subquery expected: siapa dinilai oleh siapa
        $expectedSub = DB::table('m_relation')
            ->select('nik_relasi', DB::raw('COUNT(DISTINCT nik) as total_expected'))
            ->groupBy('nik_relasi');


        // subquery done: total penilai unik yang sudah submit untuk nik_relasi di periode ini
        $doneSub = DB::table('t_penilaian_hdr as h')
            ->join('t_penilaian_dtl as d', 'd.penilaian_id', '=', 'h.id')
            ->select('d.nik_relasi', DB::raw('COUNT(DISTINCT h.nik_penilai) as total_done'))
            ->whereRaw("DATE_FORMAT(h.tanggal, '%Y-%m') = ?", [$periode])
            ->groupBy('d.nik_relasi');

        // subquery avg score: rata-rata nilai detail (nilai faktor)
        $avgSub = DB::table('t_penilaian_hdr as h')
            ->join('t_penilaian_dtl as d', 'd.penilaian_id', '=', 'h.id')
            ->select('d.nik_relasi', DB::raw('ROUND(AVG(d.nilai), 2) as avg_score'))
            ->whereRaw("DATE_FORMAT(h.tanggal, '%Y-%m') = ?", [$periode])
            ->groupBy('d.nik_relasi');

        $kategoriSub = DB::query()
            ->fromSub($avgSub, 'av2')
            ->join('m_score_range as sr', function ($join) {
                $join->on('av2.avg_score', '>=', 'sr.min_score')
                    ->on('av2.avg_score', '<=', 'sr.max_score');
            })
            ->select([
                'av2.nik_relasi',
                'sr.label as kategori',
            ]);

        $rows = DB::query()
            ->fromSub($expectedSub, 'exp')
            ->leftJoinSub($doneSub, 'dn', 'dn.nik_relasi', '=', 'exp.nik_relasi')
            ->leftJoinSub($avgSub, 'av', 'av.nik_relasi', '=', 'exp.nik_relasi')
            ->leftJoinSub($kategoriSub, 'kat', 'kat.nik_relasi', '=', 'exp.nik_relasi')
            ->leftJoin('m_karyawan as k', function ($join) {
                $join->on(
                    DB::raw('k.nik COLLATE utf8mb4_unicode_ci'),
                    '=',
                    DB::raw('exp.nik_relasi COLLATE utf8mb4_unicode_ci')
                );
            })

            ->select([
                'exp.nik_relasi',
                'k.nama_karyawan',
                'k.jabatan',
                'k.posisi',
                DB::raw('exp.total_expected'),
                DB::raw('COALESCE(dn.total_done, 0) as total_done'),
                DB::raw('COALESCE(av.avg_score, 0) as avg_score'),
                DB::raw("COALESCE(kat.kategori, '-') as kategori"),
                DB::raw(
                    'LEAST(
                        ROUND((COALESCE(dn.total_done,0) / exp.total_expected) * 100, 0),
                        100
                    ) as progress_percent'
                ),

            ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('exp.nik_relasi', 'like', "%{$search}%")
                        ->orWhere('k.nama_karyawan', 'like', "%{$search}%")
                        ->orWhere('k.posisi', 'like', "%{$search}%");
                });
            })
            ->when($kategori, function ($q) use ($kategori) {
                $q->where('kat.kategori', $kategori);
            })
            ->when($status == 'submit', function ($q) {
                $q->havingRaw('progress_percent = 100');
            })
            ->when($status == 'belum', function ($q) {
                $q->havingRaw('progress_percent < 100');
            })
            ->orderByDesc('progress_percent')
            ->get();

        // ambil avg_score per nik_relasi untuk periode tsb
        $avgPerKaryawan = DB::table('t_penilaian_hdr as h')
            ->join('t_penilaian_dtl as d', 'd.penilaian_id', '=', 'h.id')
            ->whereRaw("DATE_FORMAT(h.tanggal, '%Y-%m') = ?", [$periode])
            ->groupBy('d.nik_relasi')
            ->select([
                'd.nik_relasi',
                DB::raw('ROUND(AVG(d.nilai),2) as avg_score'),
            ]);

        // mapping avg_score -> kategori lewat range table
        $pieSummary = DB::query()
            ->fromSub($avgPerKaryawan, 'x')
            ->join('m_score_range as sr', function ($join) {
                $join->on('x.avg_score', '>=', 'sr.min_score')
                    ->on('x.avg_score', '<=', 'sr.max_score');
            })
            ->groupBy('sr.label')
            ->select([
                'sr.label',
                DB::raw('COUNT(*) as total'),
            ])
            ->orderByRaw("FIELD(sr.label,'Sangat Kurang','Kurang','Cukup','Baik','Sangat Baik')")
            ->get();

        // =====================
        // TOP 5 SCORE TERTINGGI
        // =====================
        $topScores = DB::table('t_penilaian_hdr as h')
            ->join('t_penilaian_dtl as d', 'd.penilaian_id', '=', 'h.id')
            ->join('m_karyawan as k', function ($join) {
                $join->on(
                    DB::raw('k.nik COLLATE utf8mb4_unicode_ci'),
                    '=',
                    DB::raw('d.nik_relasi COLLATE utf8mb4_unicode_ci')
                );
            })
            ->whereRaw("DATE_FORMAT(h.tanggal, '%Y-%m') = ?", [$periode])
            ->groupBy('d.nik_relasi', 'k.nama_karyawan', 'k.jabatan')
            ->select([
                'd.nik_relasi',
                'k.nama_karyawan',
                'k.jabatan',
                DB::raw('ROUND(AVG(d.nilai), 2) as avg_score'),
            ])
            ->orderByDesc('avg_score')
            ->limit(5)
            ->get();


        $pieLabels = $pieSummary->pluck('label');
        $pieValues = $pieSummary->pluck('total');
        $totalPie  = $pieValues->sum();

        return view('hr.monitoring.360', [
            'rows' => $rows,
            'periode' => $periode,
            'search' => $search,
            'pieSummary' => $pieSummary,
            'pieLabels' => $pieLabels,
            'pieValues' => $pieValues,
            'totalPie' => $totalPie,
            'kategori' => $kategori,
            'totalSubmit' => $totalSubmit,
            'totalBelumSubmit' => $totalBelumSubmit,
            'status' => $status,
            'topScores' => $topScores,
        ]);
    }

    // public function detailBelumSubmit(Request $request)
    // {
    //     $periode = $request->get('periode', now()->format('Y-m'));

    //     // Ambil semua nik_penilai yang seharusnya menilai
    //     $expectedPenilai = DB::table('m_relation')
    //         ->distinct()
    //         ->pluck('nik');

    //     // Ambil nik_penilai yang sudah submit di periode ini
    //     $sudahSubmit = DB::table('t_penilaian_hdr')
    //         ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periode])
    //         ->distinct()
    //         ->pluck('nik_penilai');

    //     // Yang belum submit = expected - sudah submit
    //     $belumSubmit = $expectedPenilai->diff($sudahSubmit);

    //     // Join dengan m_karyawan untuk ambil detail
    //     $detailBelumSubmit = DB::table('m_karyawan')
    //         ->whereIn('nik', $belumSubmit)
    //         ->select('nik', 'nama_karyawan', 'jabatan', 'posisi')
    //         ->get();

    //     return view('hr.monitoring.belum-submit', [
    //         'rows' => $detailBelumSubmit,
    //         'periode' => $periode,
    //     ]);
    // }

    public function modalSudahSubmit(Request $request)
    {
        $periode = $request->get('periode', now()->format('Y-m'));

        $rows = DB::table('t_penilaian_hdr as h')
            ->join('m_karyawan as k', 'k.nik', '=', 'h.nik_penilai')
            ->whereRaw("DATE_FORMAT(h.tanggal, '%Y-%m') = ?", [$periode])
            ->select([
                'k.nik',
                'k.nama_karyawan',
                'k.jabatan',
                DB::raw("DATE_FORMAT(h.created_at, '%d-%m-%Y %H:%i') as waktu_submit"),
            ])
            ->orderBy('h.created_at', 'desc')
            ->get();

        return response()->json($rows);
    }

    public function modalBelumSubmit(Request $request)
    {
        $periode = $request->get('periode', now()->format('Y-m'));

        $expected = DB::table('m_relation')->distinct()->pluck('nik');

        $submitted = DB::table('t_penilaian_hdr')
            ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periode])
            ->pluck('nik_penilai');

        $belum = $expected->diff($submitted);

        $rows = DB::table('m_karyawan')
            ->whereIn('nik', $belum)
            ->select('nik', 'nama_karyawan', 'jabatan')
            ->orderBy('nama_karyawan')
            ->get();

        return response()->json($rows);
    }
}