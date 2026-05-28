<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlineUserController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'city' => ['nullable', 'string', 'max:100'],
            'location_unavailable' => ['nullable', 'boolean'],
        ]);

        $attributes = [
            'last_seen_at' => now(),
        ];

        if ($payload['location_unavailable'] ?? false) {
            $attributes['online_latitude'] = null;
            $attributes['online_longitude'] = null;
            $attributes['online_city'] = null;
            $attributes['online_location_updated_at'] = null;
        } elseif (isset($payload['latitude'], $payload['longitude'])) {
            $attributes['online_latitude'] = $payload['latitude'];
            $attributes['online_longitude'] = $payload['longitude'];
            $attributes['online_city'] = $this->normalizeCity($payload['city'] ?? null);
            $attributes['online_location_updated_at'] = now();
        }

        $request->user()->forceFill($attributes)->save();

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
            'city' => $user->online_city,
            'latitude' => $user->online_latitude,
            'longitude' => $user->online_longitude,
            'photo_url' => $user->photo
                ? route('profile-photos.show', ['filename' => basename($user->photo)])
                : null,
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            'location_updated_at' => $user->online_location_updated_at?->toIso8601String(),
        ];
    }

    private function normalizeCity(?string $city): ?string
    {
        $city = trim((string) $city);

        if ($city === '') {
            return null;
        }

        $city = preg_replace('/\b(kota|kabupaten|kab\.)\b/i', '', $city);

        return trim((string) $city) ?: null;
    }
}
