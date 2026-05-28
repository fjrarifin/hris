<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlineUserController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $request->user()->forceFill([
            'last_seen_at' => now(),
        ])->save();

        return response()->json(['ok' => true]);
    }

    public function index(): JsonResponse
    {
        $users = User::query()
            ->with('karyawan')
            ->where('last_seen_at', '>=', now()->subMinutes(2))
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (User $user): array => $this->serialize($user))
            ->values();

        return response()->json(['users' => $users]);
    }

    private function serialize(User $user): array
    {
        $employee = $user->karyawan;

        return [
            'id' => $user->id,
            'name' => $employee?->nama_karyawan ?? $user->name,
            'nik' => $employee?->nik ?? $user->username,
            'position' => $employee?->jabatan ?: ($employee?->posisi ?: '-'),
            'city' => $this->cityFromEmployee($employee),
            'photo_url' => $user->photo
                ? route('profile-photos.show', ['filename' => basename($user->photo)])
                : null,
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
        ];
    }

    private function cityFromEmployee(?Karyawan $employee): string
    {
        if (! $employee) {
            return '-';
        }

        $address = trim((string) $employee->alamat);
        if ($address === '') {
            return trim((string) $employee->tempat_lahir) ?: '-';
        }

        $parts = collect(preg_split('/[,;\n]+/', $address))
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->values();

        $city = $parts->first(fn (string $part): bool => preg_match('/\b(kota|kabupaten|kab\.|jakarta|bandung|bekasi|depok|tangerang|bogor)\b/i', $part));

        if (! $city) {
            $city = $parts->last();
        }

        $city = preg_replace('/\b(kota|kabupaten|kab\.)\b/i', '', (string) $city);

        return trim((string) $city) ?: '-';
    }
}
