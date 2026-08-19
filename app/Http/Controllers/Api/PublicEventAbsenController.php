<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsensiEvent;
use App\Models\EventAbsen;
use App\Models\Karyawan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicEventAbsenController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $event = EventAbsen::query()
            ->where('slug', $slug)
            ->first();

        if (! $event) {
            return response()->json([
                'message' => 'Event absensi tidak ditemukan.',
                'error_code' => 'EVENT_NOT_FOUND',
            ], 404);
        }

        $now = now();
        $isExpired = $event->tanggal_selesai && $now->greaterThan($event->tanggal_selesai);
        $isNotStarted = $event->tanggal_mulai && $now->lessThan($event->tanggal_mulai);
        $isInactive = $event->status === 'nonaktif';
        $canAttend = ! $isExpired && ! $isInactive && ! $isNotStarted;

        return response()->json([
            'data' => [
                'id' => $event->id,
                'nama_event' => $event->nama_event,
                'deskripsi' => $event->deskripsi,
                'slug' => $event->slug,
                'tanggal_mulai' => $event->tanggal_mulai?->toIso8601String(),
                'tanggal_selesai' => $event->tanggal_selesai?->toIso8601String(),
                'tanggal_mulai_formatted' => $event->tanggal_mulai?->translatedFormat('d F Y, H:i').' WIB',
                'tanggal_selesai_formatted' => $event->tanggal_selesai?->translatedFormat('d F Y, H:i').' WIB',
                'status' => $event->status,
                'effective_status' => $event->effective_status,
                'effective_status_label' => $event->effective_status_label,
                'can_attend' => $canAttend,
                'is_expired' => $isExpired,
                'is_not_started' => $isNotStarted,
                'is_inactive' => $isInactive,
            ],
        ]);
    }

    public function validateNik(Request $request, string $slug): JsonResponse
    {
        $event = EventAbsen::query()
            ->where('slug', $slug)
            ->first();

        if (! $event) {
            return response()->json([
                'message' => 'Event absensi tidak ditemukan.',
                'error_code' => 'EVENT_NOT_FOUND',
            ], 404);
        }

        if ($event->status === 'nonaktif') {
            return response()->json([
                'message' => 'Event absensi ini sedang nonaktif.',
                'error_code' => 'EVENT_INACTIVE',
            ], 422);
        }

        if ($event->tanggal_selesai && now()->greaterThan($event->tanggal_selesai)) {
            return response()->json([
                'message' => 'Waktu absensi untuk event ini telah berakhir.',
                'error_code' => 'EVENT_EXPIRED',
            ], 422);
        }

        if ($event->tanggal_mulai && now()->lessThan($event->tanggal_mulai)) {
            return response()->json([
                'message' => 'Waktu absensi untuk event ini belum dimulai (mulai '.$event->tanggal_mulai->translatedFormat('d F Y, H:i').' WIB).',
                'error_code' => 'EVENT_NOT_STARTED',
            ], 422);
        }

        $request->validate([
            'nik' => ['required', 'string'],
        ], [
            'nik.required' => 'NIK Karyawan wajib diisi.',
        ]);

        $nik = trim($request->input('nik'));

        $karyawan = Karyawan::query()
            ->where('nik', $nik)
            ->first();

        if (! $karyawan) {
            return response()->json([
                'message' => "NIK '{$nik}' tidak terdaftar dalam database karyawan HRIS.",
                'error_code' => 'NIK_NOT_FOUND',
            ], 404);
        }

        // Check if already attended
        $existingAttendance = AbsensiEvent::query()
            ->where('id_event_absen', $event->id)
            ->where('nik_karyawan', $nik)
            ->first();

        if ($existingAttendance) {
            $formattedTime = $existingAttendance->jam_absen
                ? $existingAttendance->jam_absen->translatedFormat('d F Y, H:i:s').' WIB'
                : '-';

            return response()->json([
                'message' => "Anda sudah melakukan absensi pada event ini ({$formattedTime}). Tidak dapat absen ulang.",
                'error_code' => 'ALREADY_ATTENDED',
                'attended_at' => $existingAttendance->jam_absen?->toIso8601String(),
                'attended_at_formatted' => $formattedTime,
                'data' => [
                    'nik' => $karyawan->nik,
                    'nama_karyawan' => $karyawan->nama_karyawan,
                    'jabatan' => $karyawan->jabatan,
                    'divisi' => $karyawan->divisi,
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'NIK valid.',
            'data' => [
                'nik' => $karyawan->nik,
                'nama_karyawan' => $karyawan->nama_karyawan,
                'jabatan' => $karyawan->jabatan,
                'divisi' => $karyawan->divisi,
                'departement' => $karyawan->departement,
            ],
        ]);
    }

    public function submitAttendance(Request $request, string $slug): JsonResponse
    {
        $event = EventAbsen::query()
            ->where('slug', $slug)
            ->first();

        if (! $event) {
            return response()->json([
                'message' => 'Event absensi tidak ditemukan.',
                'error_code' => 'EVENT_NOT_FOUND',
            ], 404);
        }

        if ($event->status === 'nonaktif') {
            return response()->json([
                'message' => 'Event absensi ini sedang nonaktif.',
                'error_code' => 'EVENT_INACTIVE',
            ], 422);
        }

        if ($event->tanggal_selesai && now()->greaterThan($event->tanggal_selesai)) {
            return response()->json([
                'message' => 'Waktu absensi untuk event ini telah berakhir.',
                'error_code' => 'EVENT_EXPIRED',
            ], 422);
        }

        if ($event->tanggal_mulai && now()->lessThan($event->tanggal_mulai)) {
            return response()->json([
                'message' => 'Waktu absensi untuk event ini belum dimulai.',
                'error_code' => 'EVENT_NOT_STARTED',
            ], 422);
        }

        $request->validate([
            'nik' => ['required', 'string'],
            'photo' => ['required', 'string'],
            'user_agent' => ['nullable', 'string'],
        ], [
            'nik.required' => 'NIK Karyawan wajib diisi.',
            'photo.required' => 'Foto selfie wajib diambil.',
        ]);

        $nik = trim($request->input('nik'));

        $karyawan = Karyawan::query()->where('nik', $nik)->first();
        if (! $karyawan) {
            return response()->json([
                'message' => "NIK '{$nik}' tidak terdaftar.",
                'error_code' => 'NIK_NOT_FOUND',
            ], 404);
        }

        // Process selfie photo
        $photoPayload = $request->input('photo');
        $photoData = null;
        $extension = 'jpg';

        if (preg_match('/^data:image\/(\w+);base64,/', $photoPayload, $matches)) {
            $extension = strtolower($matches[1]);
            if ($extension === 'jpeg') {
                $extension = 'jpg';
            }
            $photoPayload = substr($photoPayload, strpos($photoPayload, ',') + 1);
        }

        $photoData = base64_decode($photoPayload);

        if ($photoData === false || strlen($photoData) === 0) {
            return response()->json([
                'message' => 'Format foto selfie tidak valid.',
                'error_code' => 'INVALID_PHOTO',
            ], 422);
        }

        // Limit size: Max 5MB raw
        if (strlen($photoData) > 5 * 1024 * 1024) {
            return response()->json([
                'message' => 'Ukuran file foto selfie melebihi batas 5MB.',
                'error_code' => 'PHOTO_TOO_LARGE',
            ], 422);
        }

        $fileName = sprintf('event-%s-%s-%s.%s', $event->id, $nik, now()->format('YmdHis'), $extension);
        $filePath = 'absensi-event/'.$fileName;

        try {
            $attendance = DB::transaction(function () use ($event, $nik, $filePath, $photoData, $request) {
                // Prevent duplicate submit race condition
                $already = AbsensiEvent::query()
                    ->where('id_event_absen', $event->id)
                    ->where('nik_karyawan', $nik)
                    ->lockForUpdate()
                    ->first();

                if ($already) {
                    abort(422, 'Anda sudah melakukan absensi pada event ini.');
                }

                // Save photo to public disk
                Storage::disk('public')->put($filePath, $photoData);

                return AbsensiEvent::create([
                    'id_event_absen' => $event->id,
                    'nik_karyawan' => $nik,
                    'foto_absen' => $filePath,
                    'jam_absen' => now(),
                    'user_agent' => $request->input('user_agent', $request->header('User-Agent')),
                    'ip_address' => $request->ip(),
                ]);
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'ALREADY_ATTENDED',
            ], $e->getStatusCode());
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json([
                'message' => 'Anda sudah melakukan absensi pada event ini.',
                'error_code' => 'ALREADY_ATTENDED',
            ], 422);
        }

        $attendance->load(['karyawan:nik,nama_karyawan,jabatan,divisi']);

        return response()->json([
            'message' => 'Absensi berhasil tercatat. Terima kasih!',
            'data' => [
                'id' => $attendance->id,
                'nama_event' => $event->nama_event,
                'nik' => $attendance->nik_karyawan,
                'nama_karyawan' => $karyawan->nama_karyawan,
                'jabatan' => $karyawan->jabatan,
                'divisi' => $karyawan->divisi,
                'jam_absen' => $attendance->jam_absen->toIso8601String(),
                'jam_absen_formatted' => $attendance->jam_absen->translatedFormat('d F Y, H:i:s').' WIB',
                'foto_url' => $attendance->foto_url,
            ],
        ], 201);
    }
}
