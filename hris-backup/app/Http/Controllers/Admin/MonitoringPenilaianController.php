<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringPenilaianController extends Controller
{
    public function index(Request $request)
    {
        $periode = $request->get('periode', now()->format('Y-m')); // contoh: 2026-01
        $kategori = $request->get('kategori'); // contoh: Baik / Cukup dll
        $search = trim($request->get('q', ''));

        // subquery expected: siapa dinilai oleh siapa
        $expectedSub = DB::table('m_relation')
            ->select('nik_relasi', DB::raw('COUNT(*) as total_expected'))
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

            // ✅ join kategori
            ->leftJoinSub($kategoriSub, 'kat', 'kat.nik_relasi', '=', 'exp.nik_relasi')

            ->leftJoin('m_karyawan as k', function ($join) {
                $join->on(DB::raw('k.nik COLLATE utf8mb4_unicode_ci'), '=', DB::raw('exp.nik_relasi COLLATE utf8mb4_unicode_ci'));
            })
            ->select([
                'exp.nik_relasi',
                'k.nama_karyawan',
                'k.jabatan',
                'k.posisi',
                DB::raw('exp.total_expected'),
                DB::raw('COALESCE(dn.total_done, 0) as total_done'),
                DB::raw('COALESCE(av.avg_score, 0) as avg_score'),

                // ✅ kategori ready
                DB::raw("COALESCE(kat.kategori, '-') as kategori"),

                DB::raw('ROUND((COALESCE(dn.total_done,0) / exp.total_expected) * 100, 0) as progress_percent'),
            ])

            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('exp.nik_relasi', 'like', "%{$search}%")
                        ->orWhere('k.nama_karyawan', 'like', "%{$search}%")
                        ->orWhere('k.posisi', 'like', "%{$search}%"); // ✅ tambah posisi
                });
            })

            ->when($kategori, function ($q) use ($kategori) {
                $q->where('kat.kategori', $kategori);
            })

            ->orderByDesc('progress_percent')
            ->paginate(15)
            ->withQueryString();

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

        $pieLabels = $pieSummary->pluck('label');
        $pieValues = $pieSummary->pluck('total');
        $totalPie  = $pieValues->sum();


        return view('admin.monitoring.index', [
            'rows' => $rows,
            'periode' => $periode,
            'search' => $search,
            'pieSummary' => $pieSummary,
            'pieLabels' => $pieLabels,
            'pieValues' => $pieValues,
            'totalPie' => $totalPie,
            'kategori' => $kategori,
        ]);
    }

    public function detail(Request $request, $nik)
    {
        $periode = $request->get('periode', now()->format('Y-m'));

        // data karyawan yang dinilai
        $karyawan = DB::table('m_karyawan')->where('nik', $nik)->first();

        // expected penilai (dari mapping relasi)
        $expectedPenilai = DB::table('m_relation as r')
            ->leftJoin('m_karyawan as k', function ($join) {
                $join->on(DB::raw('k.nik COLLATE utf8mb4_unicode_ci'), '=', DB::raw('r.nik COLLATE utf8mb4_unicode_ci'));
            })
            ->where('r.nik_relasi', $nik)
            ->select([
                'r.nik as nik_penilai',
                'k.nama_karyawan',
                'k.jabatan',
                'r.kategori_relasi',
            ])
            ->orderBy('k.nama_karyawan')
            ->get();

        // yang sudah submit nilai untuk nik_relasi ini pada periode tsb
        // ambil avg per penilai biar clean
        $donePenilai = DB::table('t_penilaian_hdr as h')
            ->join('t_penilaian_dtl as d', 'd.penilaian_id', '=', 'h.id')
            ->leftJoin('m_karyawan as k', function ($join) {
                $join->on(DB::raw('k.nik COLLATE utf8mb4_unicode_ci'), '=', DB::raw('h.nik_penilai COLLATE utf8mb4_unicode_ci'));
            })
            ->where('d.nik_relasi', $nik)
            ->whereRaw("DATE_FORMAT(h.tanggal, '%Y-%m') = ?", [$periode])
            ->groupBy('h.nik_penilai', 'k.nama_karyawan', 'k.jabatan')
            ->select([
                'h.nik_penilai',
                'k.nama_karyawan',
                'k.jabatan',
                DB::raw('ROUND(AVG(d.nilai), 2) as avg_score'),
                DB::raw('MAX(h.created_at) as submitted_at'),

                // ✅ ambil catatan (1 aja per penilai)
                DB::raw("MAX(NULLIF(d.catatan,'')) as catatan"),
            ])
            ->orderByDesc('submitted_at')
            ->get();


        // bikin list nik yang sudah menilai
        $doneNik = $donePenilai->pluck('nik_penilai')->toArray();

        // belum menilai = expected - done
        $notDonePenilai = $expectedPenilai->filter(function ($x) use ($doneNik) {
            return ! in_array($x->nik_penilai, $doneNik);
        })->values();

        // nilai akhir overall untuk nik ini (avg total semua faktor semua penilai)
        $avgFinal = DB::table('t_penilaian_hdr as h')
            ->join('t_penilaian_dtl as d', 'd.penilaian_id', '=', 'h.id')
            ->where('d.nik_relasi', $nik)
            ->whereRaw("DATE_FORMAT(h.tanggal, '%Y-%m') = ?", [$periode])
            ->select(DB::raw('ROUND(AVG(d.nilai), 2) as avg_final'))
            ->value('avg_final') ?? 0;

        // progress
        $totalExpected = $expectedPenilai->count();
        $totalDone = $donePenilai->count();
        $progressPercent = $totalExpected > 0 ? round(($totalDone / $totalExpected) * 100, 0) : 0;

        // kategori dari avg_final (pakai range table kamu)
        $kategori = $this->kategoriScore($avgFinal);

        return view('admin.monitoring.detail', [
            'periode' => $periode,
            'nik' => $nik,
            'karyawan' => $karyawan,
            'avgFinal' => $avgFinal,
            'kategori' => $kategori,
            'totalExpected' => $totalExpected,
            'totalDone' => $totalDone,
            'progressPercent' => $progressPercent,
            'donePenilai' => $donePenilai,
            'notDonePenilai' => $notDonePenilai,
            'expectedPenilai' => $expectedPenilai,
        ]);
    }

    private function kategoriScore(float $avg): string
    {
        if ($avg <= 1.86) {
            return 'Sangat Kurang';
        }
        if ($avg <= 2.86) {
            return 'Kurang';
        }
        if ($avg <= 3.70) {
            return 'Cukup';
        }
        if ($avg <= 4.70) {
            return 'Baik';
        }

        return 'Sangat Baik';
    }

    public function submitReview(Request $request)
    {
        $periode = $request->get('periode', now()->format('Y-m'));
        $search = trim($request->get('q', ''));

        // List reviewer yang sudah submit di periode ini
        $rows = DB::table('t_penilaian_hdr as h')
            ->leftJoin('m_karyawan as k', function ($join) {
                $join->on(DB::raw('k.nik COLLATE utf8mb4_unicode_ci'), '=', DB::raw('h.nik_penilai COLLATE utf8mb4_unicode_ci'));
            })
            ->whereRaw("DATE_FORMAT(h.tanggal, '%Y-%m') = ?", [$periode])
            ->select([
                'h.nik_penilai',
                'k.nama_karyawan',
                'k.jabatan',
                DB::raw('MAX(h.created_at) as submitted_at'),
                DB::raw('COUNT(DISTINCT h.id) as total_submit'),
            ])
            ->groupBy('h.nik_penilai', 'k.nama_karyawan', 'k.jabatan')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('h.nik_penilai', 'like', "%{$search}%")
                        ->orWhere('k.nama_karyawan', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('submitted_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.monitoring.submit', [
            'periode' => $periode,
            'search' => $search,
            'rows' => $rows,
        ]);
    }

    public function export(Request $request)
    {
        $periode = $request->get('periode', now()->format('Y-m'));
        $kategori = $request->get('kategori');
        $search = trim($request->get('q', ''));

        // subquery expected
        $expectedSub = DB::table('m_relation')
            ->select('nik_relasi', DB::raw('COUNT(*) as total_expected'))
            ->groupBy('nik_relasi');

        // subquery done
        $doneSub = DB::table('t_penilaian_hdr as h')
            ->join('t_penilaian_dtl as d', 'd.penilaian_id', '=', 'h.id')
            ->select('d.nik_relasi', DB::raw('COUNT(DISTINCT h.nik_penilai) as total_done'))
            ->whereRaw("DATE_FORMAT(h.tanggal, '%Y-%m') = ?", [$periode])
            ->groupBy('d.nik_relasi');

        // subquery avg score
        $avgSub = DB::table('t_penilaian_hdr as h')
            ->join('t_penilaian_dtl as d', 'd.penilaian_id', '=', 'h.id')
            ->select('d.nik_relasi', DB::raw('ROUND(AVG(d.nilai), 2) as avg_score'))
            ->whereRaw("DATE_FORMAT(h.tanggal, '%Y-%m') = ?", [$periode])
            ->groupBy('d.nik_relasi');

        // kategori sub (avg -> range)
        $kategoriSub = DB::query()
            ->fromSub(
                DB::table('t_penilaian_hdr as h')
                    ->join('t_penilaian_dtl as d', 'd.penilaian_id', '=', 'h.id')
                    ->whereRaw("DATE_FORMAT(h.tanggal, '%Y-%m') = ?", [$periode])
                    ->groupBy('d.nik_relasi')
                    ->select([
                        'd.nik_relasi',
                        DB::raw('ROUND(AVG(d.nilai),2) as avg_score'),
                    ]),
                'x'
            )
            ->join('m_score_range as sr', function ($join) {
                $join->on('x.avg_score', '>=', 'sr.min_score')
                    ->on('x.avg_score', '<=', 'sr.max_score');
            })
            ->select([
                'x.nik_relasi',
                'sr.label as kategori',
            ]);

        $rows = DB::query()
            ->fromSub($expectedSub, 'exp')
            ->leftJoinSub($doneSub, 'dn', 'dn.nik_relasi', '=', 'exp.nik_relasi')
            ->leftJoinSub($avgSub, 'av', 'av.nik_relasi', '=', 'exp.nik_relasi')
            ->leftJoinSub($kategoriSub, 'kat', 'kat.nik_relasi', '=', 'exp.nik_relasi')
            ->leftJoin('m_karyawan as k', function ($join) {
                $join->on(DB::raw('k.nik COLLATE utf8mb4_unicode_ci'), '=', DB::raw('exp.nik_relasi COLLATE utf8mb4_unicode_ci'));
            })
            ->select([
                'exp.nik_relasi',
                'k.nama_karyawan',
                'k.posisi',
                'k.jabatan',
                DB::raw('exp.total_expected'),
                DB::raw('COALESCE(dn.total_done, 0) as total_done'),
                DB::raw('COALESCE(av.avg_score, 0) as avg_score'),
                DB::raw("COALESCE(kat.kategori, '-') as kategori"),
                DB::raw('ROUND((COALESCE(dn.total_done,0) / exp.total_expected) * 100, 0) as progress_percent'),
            ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('exp.nik_relasi', 'like', "%{$search}%")
                        ->orWhere('k.nama_karyawan', 'like', "%{$search}%")
                        ->orWhere('k.jabatan', 'like', "%{$search}%"); // ✅ cari via jabatan juga
                });
            })
            ->when($kategori, function ($q) use ($kategori) {
                $q->where('kat.kategori', $kategori);
            })
            ->orderByDesc('progress_percent')
            ->get();

        // ===== OUTPUT CSV =====
        $fileName = "monitoring_penilaian_{$periode}.csv";

        $headers = [
            "Content-Type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename={$fileName}",
        ];

        $callback = function () use ($rows, $periode) {
            $handle = fopen('php://output', 'w');

            // BOM biar Excel Indo aman UTF-8
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // header kolom
            fputcsv($handle, [
                'Periode',
                'NIK',
                'Nama Karyawan',
                'Posisi',
                'Jabatan',
                'Total Penilai',
                'Sudah Menilai',
                'Progress (%)',
                'Avg Score',
                'Kategori',
            ]);

            foreach ($rows as $r) {
                fputcsv($handle, [
                    $periode,
                    $r->nik_relasi,
                    $r->nama_karyawan,
                    $r->posisi,
                    $r->jabatan,
                    $r->total_expected,
                    $r->total_done,
                    $r->progress_percent,
                    $r->avg_score,
                    $r->kategori,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
