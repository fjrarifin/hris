<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PenilaianDtl;
use App\Models\PenilaianHdr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PenilaianController extends Controller
{
    public function index()
    {

        $nikPenilai = Auth::user()->username;
        $periode = now()->format('Y-m');

        /*
        |--------------------------------------------------------------------------
        | 1. Ambil SEMUA relasi yg harus dinilai
        |--------------------------------------------------------------------------
        */
        $relasi = DB::table('m_relation as r')
            ->leftJoin('m_karyawan as k', DB::raw('k.nik COLLATE utf8mb4_unicode_ci'), '=', DB::raw('r.nik_relasi COLLATE utf8mb4_unicode_ci'))
            ->where('r.nik', $nikPenilai)
            ->select(
                'r.nik_relasi',
                'k.nama_karyawan',
                'k.jabatan',
                'k.posisi',
            )
            ->get();

        if ($relasi->isEmpty()) {
            return view('staff.performance.index', [
                'relasi' => collect(),
                'faktorByRelasi' => [],
                'skala' => $this->skala(),
                'periode' => $periode,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Ambil relasi yang SUDAH DINILAI user di periode ini
        |--------------------------------------------------------------------------
        */
        $relasiSudahDinilai = DB::table('t_penilaian_hdr as h')
            ->join('t_penilaian_dtl as d', DB::raw('d.penilaian_id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('h.id COLLATE utf8mb4_unicode_ci'))
            ->where('h.nik_penilai', $nikPenilai)
            ->where('h.periode', $periode)
            ->pluck('d.nik_relasi')
            ->unique()
            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | 3. Filter relasi yang BELUM DINILAI
        |--------------------------------------------------------------------------
        */
        $relasiBelumDinilai = $relasi->filter(function ($r) use ($relasiSudahDinilai) {
            return !in_array($r->nik_relasi, $relasiSudahDinilai);
        })->values();

        /*
        |--------------------------------------------------------------------------
        | 4. Kalau SEMUA sudah dinilai → tampilkan submit success
        |--------------------------------------------------------------------------
        */
        if ($relasiBelumDinilai->isEmpty()) {
            $tanggalSubmit = DB::table('t_penilaian_hdr')
                ->where('nik_penilai', $nikPenilai)
                ->where('periode', $periode)
                ->max('created_at');

            return view('staff.performance.submit', [
                'periode' => $periode,
                'tanggal_submit' => $tanggalSubmit,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | 5. Ambil FAKTOR hanya untuk relasi yang BELUM DINILAI
        |--------------------------------------------------------------------------
        */
        $faktorByRelasi = [];

        foreach ($relasiBelumDinilai as $r) {
            $levelName = $r->posisi ?: 'Jr. Staff';

            $faktorByRelasi[$r->nik_relasi] = DB::table('m_penilaian_template as t')
                ->join('m_penilaian_level as lv', DB::raw('lv.id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('t.level_id COLLATE utf8mb4_unicode_ci'))
                ->join('m_penilaian_faktor as f', DB::raw('f.id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('t.faktor_id COLLATE utf8mb4_unicode_ci'))
                ->where('lv.nama_level', $levelName)
                ->where('t.is_active', 1)
                ->where('f.is_active', 1)
                ->orderBy('t.urutan')
                ->select('f.id', 'f.nama_faktor', 'f.deskripsi')
                ->get()
                ->map(function ($faktor) {
                    $faktor->scores = DB::table('m_penilaian_faktor_score')
                        ->where('faktor_id', $faktor->id)
                        ->orderBy('score')
                        ->get();

                    return $faktor;
                });
        }

        return view('staff.performance.index', [
            'relasi' => $relasiBelumDinilai,
            'faktorByRelasi' => $faktorByRelasi,
            'skala' => $this->skala(),
            'periode' => $periode,
        ]);
    }

    public function store(Request $request)
    {
        $nikPenilai = Auth::user()->username;
        $periode = now()->format('Y-m');

        $request->validate([
            'penilaian' => 'required|array|min:1',
        ]);

        // 🔥 ambil relasi yg sudah dinilai
        $relasiSudahDinilai = DB::table('t_penilaian_hdr as h')
            ->join('t_penilaian_dtl as d', DB::raw('d.penilaian_id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('h.id COLLATE utf8mb4_unicode_ci'))
            ->where('h.nik_penilai', $nikPenilai)
            ->where('h.periode', $periode)
            ->pluck('d.nik_relasi')
            ->unique()
            ->toArray();

        DB::transaction(function () use ($request, $periode, $nikPenilai, $relasiSudahDinilai) {

            // ✅ buat HDR baru SETIAP SUBMIT
            $hdr = PenilaianHdr::create([
                'nik_penilai' => $nikPenilai,
                'tanggal' => now()->toDateString(),
                'periode' => $periode,
                'total_relasi' => count($request->penilaian),
            ]);

            foreach ($request->penilaian as $nikRelasi => $payload) {

                // ⛔ skip kalau relasi ini sudah pernah dinilai
                if (in_array($nikRelasi, $relasiSudahDinilai)) {
                    continue;
                }

                $target = DB::table('m_karyawan')->where('nik', $nikRelasi)->first();
                $levelName = $target->posisi ?? 'Jr. Staff';

                $faktorWajib = DB::table('m_penilaian_template as t')
                    ->join('m_penilaian_level as lv', DB::raw('lv.id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('t.level_id COLLATE utf8mb4_unicode_ci'))
                    ->where('lv.nama_level', $levelName)
                    ->where('t.is_active', 1)
                    ->orderBy('t.urutan')
                    ->pluck('t.faktor_id')
                    ->toArray();

                foreach ($faktorWajib as $faktorId) {
                    if (!isset($payload[$faktorId])) {
                        throw new \Exception("Penilaian {$nikRelasi} belum lengkap.");
                    }

                    PenilaianDtl::create([
                        'penilaian_id' => $hdr->id,
                        'nik_relasi' => $nikRelasi,
                        'faktor_id' => $faktorId,
                        'nilai' => (int) $payload[$faktorId],
                        'catatan' => $payload['catatan'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('staff.performance.index')
            ->with('success', 'Penilaian berhasil disimpan ✅');
    }


    private function skala(): array
    {
        return [
            1 => 'Sangat Kurang',
            2 => 'Kurang',
            3 => 'Cukup',
            4 => 'Baik',
            5 => 'Sangat Baik',
        ];
    }
}
