<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VisitorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicVisitorController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_identitas' => ['required', 'string', 'max:50'],
            'nama_visitor' => ['required', 'string', 'max:150'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'instansi' => ['nullable', 'string', 'max:150'],
            'tujuan_bertemu' => ['nullable', 'string', 'max:150'],
            'keperluan' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'nomor_identitas.required' => 'Nomor identitas (KTP/SIM/Paspor) wajib diisi.',
            'nama_visitor.required' => 'Nama lengkap pengunjung wajib diisi.',
            'keperluan.required' => 'Keperluan kunjungan wajib diisi.',
            'keperluan.min' => 'Keperluan kunjungan minimal 3 karakter.',
        ]);

        $dateCode = now()->format('ymd');
        $todayCount = VisitorLog::whereDate('waktu_masuk', now()->toDateString())->count();
        $nomorKunjungan = sprintf('VIS-%s-%04d', $dateCode, $todayCount + 1);

        // Ensure uniqueness
        if (VisitorLog::where('nomor_kunjungan', $nomorKunjungan)->exists()) {
            $nomorKunjungan = sprintf('VIS-%s-%04d-%s', $dateCode, $todayCount + 1, strtoupper(substr(uniqid(), -3)));
        }

        $log = VisitorLog::create([
            'nomor_kunjungan' => $nomorKunjungan,
            'nomor_identitas' => strtoupper(trim((string) $validated['nomor_identitas'])),
            'nama_visitor' => trim((string) $validated['nama_visitor']),
            'no_hp' => ! empty($validated['no_hp']) ? trim((string) $validated['no_hp']) : null,
            'instansi' => ! empty($validated['instansi']) ? trim((string) $validated['instansi']) : null,
            'tujuan_bertemu' => ! empty($validated['tujuan_bertemu']) ? trim((string) $validated['tujuan_bertemu']) : null,
            'keperluan' => trim((string) $validated['keperluan']),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'waktu_masuk' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registrasi kunjungan berhasil disimpan.',
            'data' => [
                'id' => $log->id,
                'nomor_kunjungan' => $log->nomor_kunjungan,
                'nomor_identitas' => $log->nomor_identitas,
                'nama_visitor' => $log->nama_visitor,
                'instansi' => $log->instansi ?: '-',
                'tujuan_bertemu' => $log->tujuan_bertemu ?: '-',
                'keperluan' => $log->keperluan,
                'waktu_masuk' => $log->waktu_masuk->format('d/m/Y H:i:s'),
            ],
        ], 201);
    }
}
