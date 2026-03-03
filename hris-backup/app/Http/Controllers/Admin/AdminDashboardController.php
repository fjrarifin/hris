<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $totalKaryawan = DB::table('m_karyawan')->count();
        $totalUsers = DB::table('users')->count();
        $totalRelasi = DB::table('m_relation')->count();

        $bulanIni = now()->format('Y-m');
        $totalSubmitBulanIni = DB::table('t_penilaian_hdr')
            ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$bulanIni])
            ->count();

        // ✅ 10 karyawan join terbaru
        $karyawanTerbaru = DB::table('m_karyawan')
            ->whereNotNull('join_date')
            ->orderByDesc('join_date')
            ->limit(10)
            ->get();

        $kontrakHabisSebulan = DB::table('t_kontrak_karyawan as kk')
            ->leftJoin('m_karyawan as k', 'k.nik', '=', 'kk.nik')
            ->where('kk.status_kontrak', 'AKTIF')
            ->whereNotNull('kk.end_date')
            ->whereBetween('kk.end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->orderBy('kk.end_date', 'asc')
            ->select(
                'kk.nik',
                'k.nama_karyawan',
                'k.jabatan',
                'kk.kontrak_ke',
                'kk.start_date',
                'kk.end_date'
            )
            ->limit(10)
            ->get();

        // dd($kontrakHabisSebulan);

        return view('admin.dashboard', compact(
            'totalKaryawan',
            'totalUsers',
            'totalRelasi',
            'totalSubmitBulanIni',
            'karyawanTerbaru',
            'kontrakHabisSebulan',
        ));
    }
}
