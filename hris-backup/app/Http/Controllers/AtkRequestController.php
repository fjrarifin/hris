<?php

namespace App\Http\Controllers;

use App\Models\PengajuanAtk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AtkRequestController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            // Debug: cek user dan nik
            if (!$user) {
                dd('User tidak terautentikasi');
            }

            if (!isset($user->nik)) {
                dd('User tidak memiliki NIK', $user);
            }

            $q = $request->get('q', '');

            // Query hanya pengajuan milik user yang login
            $rows = PengajuanAtk::where('nik', $user->nik)
                ->when($q, function ($query) use ($q) {
                    $query->where(function ($subQuery) use ($q) {
                        $subQuery->where('request_no', 'like', '%' . $q . '%')
                            ->orWhere('nama_barang', 'like', '%' . $q . '%');
                    });
                })
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return view('user.atk.index', compact('rows', 'q'));
        } catch (\Exception $e) {
            // Tampilkan error detail
            dd([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_barang' => 'required|string|max:255',
            'qty' => 'required|integer|min:1',
            'satuan' => 'required|string|max:50',
            'keterangan' => 'nullable|string',
        ], [
            'nama_barang.required' => 'Nama barang wajib diisi',
            'qty.required' => 'Qty wajib diisi',
            'qty.min' => 'Qty minimal 1',
            'satuan.required' => 'Satuan wajib diisi',
        ]);

        $user = Auth::user();

        PengajuanAtk::create([
            'request_no' => PengajuanAtk::generateRequestNo(),
            'nik' => $user->nik,
            'nama_barang' => $validated['nama_barang'],
            'qty' => $validated['qty'],
            'satuan' => $validated['satuan'],
            'keterangan' => $validated['keterangan'] ?? null,
            'tanggal_pengajuan' => Carbon::now(),
            'status' => 'SUBMIT',
        ]);

        return redirect()->route('atk.index')
            ->with('success', 'Pengajuan ATK berhasil disubmit!');
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $pengajuan = PengajuanAtk::where('id', $id)
            ->where('nik', $user->nik)
            ->whereIn('status', ['DRAFT', 'SUBMIT'])
            ->firstOrFail();

        $pengajuan->delete();

        return redirect()->route('atk.index')
            ->with('success', 'Pengajuan ATK berhasil dihapus!');
    }
}
