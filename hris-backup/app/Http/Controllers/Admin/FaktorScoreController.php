<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaktorScoreController extends Controller
{
    /**
     * INDEX: tampilkan semua level (child & parent)
     * tombol Kelola selalu mengarah ke template parent
     */
    public function index(Request $request)
    {
        $q = $request->q;

        // ✅ hanya parent level
        $levels = DB::table('m_penilaian_level')
            ->when($q, function ($query) use ($q) {
                $query->where('nama_level', 'like', "%{$q}%");
            })
            ->whereNull('template_parent_id')
            ->orderBy('id')
            ->get();

        // hitung jumlah faktor aktif per level parent
        $templateCount = DB::table('m_penilaian_template')
            ->select('level_id', DB::raw('COUNT(*) as total'))
            ->where('is_active', 1)
            ->groupBy('level_id')
            ->pluck('total', 'level_id')
            ->toArray();

        return view('admin.faktor_score.level_index', compact('levels', 'templateCount', 'q'));
    }

    /**
     * PAGE: list faktor untuk level (yang dipakai template parent)
     */
    public function level($levelId)
    {
        $level = DB::table('m_penilaian_level')->where('id', $levelId)->first();
        if (! $level) {
            abort(404);
        }

        $templateLevelId = $level->template_parent_id ?: $level->id;

        // kalau user klik level child, arahkan ke parent (biar konsisten)
        if ($templateLevelId != $level->id) {
            return redirect()->route('admin.faktor-score.level', $templateLevelId);
        }

        $templates = DB::table('m_penilaian_template as t')
            ->leftJoin('m_penilaian_faktor as f', 'f.id', '=', 't.faktor_id')
            ->where('t.level_id', $templateLevelId)
            ->where('t.is_active', 1)
            ->orderBy('t.urutan')
            ->select(
                't.id as template_id',
                't.level_id',
                't.faktor_id',
                't.urutan',
                't.bobot',
                'f.kode',
                'f.nama_faktor',
                'f.deskripsi'
            )
            ->get();

        // indikator yang harusnya sesuai indikator level
        $indikatorNeed = (int) $level->indikator_total;

        // cek total faktor yang ada
        $indikatorHave = $templates->count();

        return view('admin.faktor_score.level_detail', compact(
            'level',
            'templateLevelId',
            'templates',
            'indikatorNeed',
            'indikatorHave'
        ));
    }

    /**
     * AUTO GENERATE score 1-5 untuk semua faktor yang belum punya
     */
    public function generateDefault($levelId)
    {
        $level = DB::table('m_penilaian_level')->where('id', $levelId)->first();
        if (! $level) {
            abort(404);
        }

        $templateLevelId = $level->template_parent_id ?: $level->id;

        // generate default cuma boleh di parent
        if ($templateLevelId != $level->id) {
            return redirect()->route('admin.faktor-score.level', $templateLevelId);
        }

        $faktorIds = DB::table('m_penilaian_template')
            ->where('level_id', $templateLevelId)
            ->where('is_active', 1)
            ->pluck('faktor_id')
            ->toArray();

        foreach ($faktorIds as $faktorId) {
            $exist = DB::table('m_penilaian_faktor_score')
                ->where('faktor_id', $faktorId)
                ->count();

            // harusnya 5 score, tapi kalau belum lengkap, kita isi yang kosong
            for ($score = 1; $score <= 5; $score++) {
                $cek = DB::table('m_penilaian_faktor_score')
                    ->where('faktor_id', $faktorId)
                    ->where('score', $score)
                    ->exists();

                if (! $cek) {
                    $namaFaktor = DB::table('m_penilaian_faktor')->where('id', $faktorId)->value('nama_faktor');

                    DB::table('m_penilaian_faktor_score')->insert([
                        'faktor_id' => $faktorId,
                        'score' => $score,
                        'deskripsi' => "Deskripsi score {$score} untuk faktor {$namaFaktor}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return redirect()->back()->with('success', 'Default score 1-5 berhasil dibuat ✅');
    }

    /**
     * Edit score 1-5 per faktor
     */
    public function edit($levelId, $faktorId)
    {
        $level = DB::table('m_penilaian_level')->where('id', $levelId)->first();
        if (! $level) {
            abort(404);
        }

        $templateLevelId = $level->template_parent_id ?: $level->id;

        if ($templateLevelId != $level->id) {
            return redirect()->route('admin.faktor-score.edit', [$templateLevelId, $faktorId]);
        }

        $faktor = DB::table('m_penilaian_faktor')->where('id', $faktorId)->first();
        if (! $faktor) {
            abort(404);
        }

        // ambil score 1-5
        $scores = DB::table('m_penilaian_faktor_score')
            ->where('faktor_id', $faktorId)
            ->orderBy('score')
            ->get();

        // auto jika kosong -> buat dulu
        if ($scores->count() < 5) {
            for ($score = 1; $score <= 5; $score++) {
                $cek = DB::table('m_penilaian_faktor_score')
                    ->where('faktor_id', $faktorId)
                    ->where('score', $score)
                    ->exists();

                if (! $cek) {
                    DB::table('m_penilaian_faktor_score')->insert([
                        'faktor_id' => $faktorId,
                        'score' => $score,
                        'deskripsi' => "Deskripsi score {$score} untuk faktor {$faktor->nama_faktor}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $scores = DB::table('m_penilaian_faktor_score')
                ->where('faktor_id', $faktorId)
                ->orderBy('score')
                ->get();
        }

        return view('admin.faktor_score.edit', compact('level', 'faktor', 'scores'));
    }

    public function update(Request $request, $levelId, $faktorId)
    {
        $request->validate([
            'deskripsi' => 'required|array',
        ]);

        foreach ($request->deskripsi as $score => $desc) {
            DB::table('m_penilaian_faktor_score')
                ->where('faktor_id', $faktorId)
                ->where('score', $score)
                ->update([
                    'deskripsi' => $desc,
                    'updated_at' => now(),
                ]);
        }

        return redirect()->back()->with('success', 'Deskripsi score berhasil disimpan ✅');
    }
}
