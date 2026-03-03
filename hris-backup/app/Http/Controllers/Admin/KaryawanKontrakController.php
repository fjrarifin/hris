<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KaryawanKontrakController extends Controller
{
    public function store(Request $request, $nik)
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'durasi_bulan' => ['required', 'integer', 'min:1', 'max:60'],
            'catatan' => ['nullable', 'string'],
        ]);

        // pastikan karyawan ada
        $karyawan = DB::table('m_karyawan')->where('nik', $nik)->first();
        if (!$karyawan) {
            return back()->with('error', 'Karyawan tidak ditemukan.');
        }

        // ambil kontrak terakhir (kontrak_ke terbesar)
        $lastKontrak = DB::table('t_kontrak_karyawan')
            ->where('nik', $nik)
            ->orderByDesc('kontrak_ke')
            ->first();

        $nextKontrakKe = $lastKontrak ? ((int)$lastKontrak->kontrak_ke + 1) : 1;

        // auto hitung end_date
        $start = Carbon::parse($request->start_date);
        $end = $start->copy()->addMonthsNoOverflow((int)$request->durasi_bulan)->subDay();

        // kalau mau: otomatis kontrak sebelumnya jadi selesai
        DB::table('t_kontrak_karyawan')
            ->where('nik', $nik)
            ->where('status_kontrak', 'AKTIF')
            ->update([
                'status_kontrak' => 'SELESAI',
                'updated_at' => now(),
            ]);

        DB::table('t_kontrak_karyawan')->insert([
            'nik' => $nik,
            'kontrak_ke' => $nextKontrakKe,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'durasi_bulan' => (int)$request->durasi_bulan,
            'status_kontrak' => 'AKTIF',
            'catatan' => $request->catatan,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Kontrak berhasil ditambahkan ✅');
    }

    public function finish(Request $request, $nik, $id)
    {
        $row = DB::table('t_kontrak_karyawan')
            ->where('id', $id)
            ->where('nik', $nik)
            ->first();

        if (!$row) {
            return back()->with('error', 'Data kontrak tidak ditemukan.');
        }

        DB::table('t_kontrak_karyawan')
            ->where('id', $id)
            ->update([
                'status_kontrak' => 'SELESAI',
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Kontrak ditandai selesai ✅');
    }

    public function destroy(Request $request, $nik, $id)
    {
        $row = DB::table('t_kontrak_karyawan')
            ->where('id', $id)
            ->where('nik', $nik)
            ->first();

        if (!$row) {
            return back()->with('error', 'Data kontrak tidak ditemukan.');
        }

        DB::table('t_kontrak_karyawan')
            ->where('id', $id)
            ->delete();

        return back()->with('success', 'Kontrak berhasil dihapus ✅');
    }
}
