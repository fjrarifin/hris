<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KaryawanController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->q;

        $karyawan = Karyawan::query()
            ->when($q, function ($query) use ($q) {
                $query->where('nik', 'like', "%$q%")
                    ->orWhere('nama_karyawan', 'like', "%$q%")
                    ->orWhere('jabatan', 'like', "%$q%")
                    ->orWhere('divisi', 'like', "%$q%");
            })
            ->orderBy('nama_karyawan')
            ->paginate(15)
            ->withQueryString();

        return view('admin.karyawan.index', compact('karyawan', 'q'));
    }

    public function create()
    {
        return view('admin.karyawan.form', [
            'mode' => 'create',
            'data' => new Karyawan(),
            'kontrak' => collect(),
            'kontrakAktif' => null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nik' => ['required', 'string', 'max:30', 'unique:m_karyawan,nik'],
            'nama_karyawan' => ['required', 'string', 'max:150'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'posisi' => ['nullable', 'string', 'max:100'],
            'divisi' => ['nullable', 'string', 'max:100'],
            'departement' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'nama_atasan_langsung' => ['nullable', 'string', 'max:150'],
            'status_kontrak' => ['nullable', 'string', 'max:50'],
            'join_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'durasi_kontrak' => ['nullable', 'numeric'],
            'end_date' => ['nullable', 'date'],
            'total_masa_kerja' => ['nullable', 'string', 'max:50'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
        ]);

        Karyawan::create($request->all());

        return redirect()->route('admin.karyawan.index')->with('success', 'Karyawan berhasil ditambahkan ✅');
    }

    public function edit($nik)
    {
        $data = Karyawan::where('nik', $nik)->firstOrFail();

        $kontrak = DB::table('t_kontrak_karyawan')
            ->where('nik', $nik)
            ->orderByDesc('kontrak_ke')
            ->get();

        $kontrakAktif = DB::table('t_kontrak_karyawan')
            ->where('nik', $nik)
            ->where('status_kontrak', 'AKTIF')
            ->orderByDesc('kontrak_ke')
            ->first();

        return view('admin.karyawan.form', [
            'mode' => 'edit',
            'data' => $data,
            'kontrak' => $kontrak,
            'kontrakAktif' => $kontrakAktif,
        ]);
    }

    public function update(Request $request, $nik)
    {
        $data = Karyawan::where('nik', $nik)->firstOrFail();

        $request->validate([
            'nama_karyawan' => ['required', 'string', 'max:150'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'posisi' => ['nullable', 'string', 'max:100'],
            'divisi' => ['nullable', 'string', 'max:100'],
            'departement' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'nama_atasan_langsung' => ['nullable', 'string', 'max:150'],
            'status_kontrak' => ['nullable', 'string', 'max:50'],
            'join_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'durasi_kontrak' => ['nullable', 'numeric'],
            'end_date' => ['nullable', 'date'],
            'total_masa_kerja' => ['nullable', 'string', 'max:50'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
        ]);

        $data->update($request->all());

        return redirect()->route('admin.karyawan.index')->with('success', 'Karyawan berhasil diupdate ✅');
    }

    public function destroy($nik)
    {
        $data = Karyawan::where('nik', $nik)->firstOrFail();
        $data->delete();

        return redirect()->route('admin.karyawan.index')->with('success', 'Karyawan berhasil dihapus ✅');
    }
}
