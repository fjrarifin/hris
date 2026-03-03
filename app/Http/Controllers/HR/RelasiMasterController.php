<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelasiMasterController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->q;

        $list = DB::table('m_karyawan as k')
            ->leftJoin('m_relation as r', 'r.nik_relasi', '=', 'k.nik')
            ->select(
                'k.nik',
                'k.nama_karyawan',
                'k.jabatan',
                DB::raw('COUNT(r.id) as total_penilai')
            )
            ->when($q, function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('k.nik', 'like', "%$q%")
                        ->orWhere('k.nama_karyawan', 'like', "%$q%")
                        ->orWhere('k.jabatan', 'like', "%$q%");
                });
            })
            ->groupBy('k.nik', 'k.nama_karyawan', 'k.jabatan')
            ->orderBy('k.nama_karyawan')
            ->get(); // 🔥 PENTING


        return view('hr.relasi.index', compact('list', 'q'));
    }

    public function detail($nik)
    {
        $karyawan = DB::table('m_karyawan')
            ->where('nik', $nik)
            ->first();

        abort_if(! $karyawan, 404);

        // siapa saja penilai yang menilai dia (master mapping)
        $penilai = DB::table('m_relation as r')
            ->leftJoin('m_karyawan as k', 'k.nik', '=', 'r.nik')
            ->where('r.nik_relasi', $nik)
            ->select(
                'r.nik',
                'k.nama_karyawan',
                'k.jabatan',
                'r.kategori_relasi'
            )
            ->orderBy('k.nama_karyawan')
            ->get();

        return view('hr.relasi.detail', compact('karyawan', 'penilai'));
    }

    public function store(Request $request, $nik)
    {
        // $nik = nik_relasi (yang dinilai)

        $request->validate([
            'nik_penilai' => ['required', 'string'],
        ]);

        // pastiin target relasi ada
        $karyawan = DB::table('m_karyawan')->where('nik', $nik)->first();
        if (!$karyawan) {
            return back()->with('error', 'Karyawan tujuan tidak ditemukan.');
        }

        $nikPenilai = trim($request->nik_penilai);

        // ga boleh nilai diri sendiri
        // if ($nikPenilai === $nik) {
        //     return back()->with('error', 'Tidak boleh menambahkan relasi ke diri sendiri.');
        // }

        // pastiin penilai ada
        $penilai = DB::table('m_karyawan')->where('nik', $nikPenilai)->first();
        if (!$penilai) {
            return back()->with('error', 'NIK penilai tidak ditemukan di master karyawan.');
        }

        // cek duplikat
        $exists = DB::table('m_relation')
            ->where('nik', $nikPenilai)
            ->where('nik_relasi', $nik)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Relasi ini sudah ada (duplikat).');
        }

        DB::table('m_relation')->insert([
            'nik' => $nikPenilai,
            'nik_relasi' => $nik,
            'kategori_relasi' => NULL,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Relasi berhasil ditambahkan ✅');
    }

    public function destroy(Request $request, $nik)
    {
        // $nik = nik_relasi (yang dinilai)

        $request->validate([
            'nik_penilai' => ['required', 'string'],
        ]);

        $nikPenilai = trim($request->nik_penilai);

        $deleted = DB::table('m_relation')
            ->where('nik', $nikPenilai)
            ->where('nik_relasi', $nik)
            ->delete();

        if (!$deleted) {
            return back()->with('error', 'Relasi gagal dihapus / tidak ditemukan.');
        }

        return back()->with('success', 'Relasi berhasil dihapus ✅');
    }
}