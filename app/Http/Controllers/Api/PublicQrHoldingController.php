<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KaryawanHolding;
use App\Models\QrHoldingTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicQrHoldingController extends Controller
{
    public function validateAndGenerate(Request $request): JsonResponse
    {
        $request->validate([
            'nik' => ['required', 'string', 'max:50'],
        ], [
            'nik.required' => 'NIK Karyawan Holding wajib diisi.',
        ]);

        $nikInput = strtoupper(trim((string) $request->input('nik')));

        if ($nikInput === '') {
            throw ValidationException::withMessages([
                'nik' => ['NIK Karyawan Holding tidak boleh kosong.'],
            ]);
        }

        $karyawan = KaryawanHolding::query()
            ->where(function ($query) use ($nikInput): void {
                $query->whereRaw('UPPER(TRIM(nik)) = ?', [$nikInput])
                    ->orWhere('nik', $nikInput);
            })
            ->where('is_active', true)
            ->first();

        if (! $karyawan) {
            return response()->json([
                'message' => 'NIK Karyawan Holding tidak ditemukan atau tidak aktif. Silakan hubungi IT Administrator.',
            ], 404);
        }

        $dateCode = now()->format('ymd');
        $nikHolding = trim((string) $karyawan->nik);

        // Turnstile Gate QR Code payload standard
        $payload = [
            't' => $dateCode . substr($nikHolding, -4),
            'm' => $nikHolding,
            'c' => $dateCode,
            'x' => [[4, 100, 374]],
        ];
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

        // Log QR generation transaction
        try {
            QrHoldingTransaction::create([
                'm_karyawan_holding_id' => $karyawan->id,
                'nik' => $nikHolding,
                'nama' => $karyawan->nama,
                'perusahaan' => $karyawan->perusahaan,
                'qr_payload' => $payloadJson,
                'access_date_code' => $dateCode,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'generated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'karyawan' => [
                'nik' => $nikHolding,
                'nama' => $karyawan->nama,
                'jabatan' => $karyawan->jabatan ?: '-',
                'departemen' => $karyawan->departemen ?: '-',
                'perusahaan' => $karyawan->perusahaan ?: 'Hompimplay Holding',
            ],
            'qr_payload' => $payload,
            'qr_payload_string' => $payloadJson,
            'access_date_code' => $dateCode,
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
