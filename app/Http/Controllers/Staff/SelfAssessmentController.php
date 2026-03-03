<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SelfAssessmentController extends Controller
{
    public function index()
    {
        $username = Auth::user()->username;
        $periode = now()->format('Y-m');

        $self = DB::table('t_penilaian_self')
            ->where('nik', $username)
            ->where('periode', $periode)
            ->first();

        // ✅ kalau sudah submit → tampilkan halaman submit
        if ($self && $self->submitted_at) {
            return view('staff.self-assessment.submitted', [
                'periode' => $periode,
                'tanggal_submit' => $self->submitted_at,
            ]);
        }

        return view('staff.self-assessment.index', [
            'self' => $self,
            'periode' => $periode,
        ]);
    }

    public function store(Request $request)
    {
        $username = Auth::user()->username;
        $periode = now()->format('Y-m');

        $data = $request->validate([
            'kesulitan' => 'required|min:10',
            'improvement' => 'required|min:10',
            'perbaikan_hompimplay' => 'required|min:10',
            'catatan_rekan' => 'required|min:10',
        ]);

        DB::table('t_penilaian_self')->updateOrInsert(
            [
                'nik' => $username,
                'periode' => $periode,
            ],
            array_merge($data, [
                'submitted_at' => now(), // 🔥 FINAL SUBMIT
                'updated_at' => now(),
                'created_at' => now(),
            ])
        );

        return redirect()
            ->route('staff.self-assessment.index')
            ->with('success', 'Self assessment berhasil disubmit');
    }
}
