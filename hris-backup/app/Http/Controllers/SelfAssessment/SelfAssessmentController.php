<?php

namespace App\Http\Controllers\SelfAssessment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SelfAssessmentController extends Controller
{
    public function store(Request $request)
    {
        $nik = Auth::user()->nik;
        $periode = now()->format('Y-m');

        $request->validate([
            'kesulitan'     => 'required|string|min:10',
            'improvement'   => 'required|string|min:10',
            'perbaikan_hompimplay'   => 'required|string|min:10',
            'catatan_rekan' => 'nullable|string',
        ]);

        DB::table('t_penilaian_self')->updateOrInsert(
            [
                'nik' => $nik,
                'periode' => $periode,
            ],
            [
                'kesulitan'     => $request->kesulitan,
                'improvement'   => $request->improvement,
                'perbaikan_hompimplay'   => $request->perbaikan_hompimplay,
                'catatan_rekan' => $request->catatan_rekan,
                'updated_at'    => now(),
                'created_at'    => now(),
            ]
        );

        return back()->with('success', 'Self assessment tersimpan ✅');
    }
}
