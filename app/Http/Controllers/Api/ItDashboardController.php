<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class ItDashboardController extends Controller
{
    private const PORTAL_TOKEN_NAME = 'hris-fe';

    private const MOBILE_TOKEN_NAME = 'hris-mobile';

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_q' => ['nullable', 'string', 'max:100'],
        ]);

        $userKeyword = trim((string) ($validated['user_q'] ?? ''));
        $activeSessions = $this->activeSessionQuery();
        $onlineNow = User::query()
            ->where('last_seen_at', '>=', now()->subMinutes(2))
            ->count();

        return response()->json([
            'summary' => [
                'online_now' => $onlineNow,
                'active_sessions' => [
                    'total' => (clone $activeSessions)->count(),
                    'web' => (clone $activeSessions)->where('name', self::PORTAL_TOKEN_NAME)->count(),
                    'mobile' => (clone $activeSessions)->where('name', self::MOBILE_TOKEN_NAME)->count(),
                ],
                'users' => [
                    'total' => User::query()->count(),
                    'active' => User::query()->where('is_active', true)->count(),
                    'inactive' => User::query()->where('is_active', false)->count(),
                ],
            ],
            'active_sessions' => (clone $activeSessions)
                ->orderByDesc('last_used_at')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
                ->map(fn (PersonalAccessToken $token): array => $this->serializeSession($token, $request))
                ->values(),
            'latest_employees' => Karyawan::query()
                ->orderByDesc('id')
                ->limit(6)
                ->get()
                ->map(fn (Karyawan $employee): array => $this->serializeEmployee($employee))
                ->values(),
            'users' => $this->userQuickList($userKeyword),
            'filters' => [
                'user_q' => $userKeyword,
            ],
        ]);
    }

    public function destroySession(Request $request, PersonalAccessToken $token): JsonResponse
    {
        abort_unless($this->isManagedToken($token), 404);

        if ((int) ($request->user()?->currentAccessToken()?->id ?? 0) === (int) $token->id) {
            abort(422, 'Gunakan menu logout biasa untuk mengakhiri sesi Anda sendiri.');
        }

        $user = $token->tokenable instanceof User ? $token->tokenable : null;
        $token->delete();
        $this->clearLastSeenIfNoActiveSession($user);

        return response()->json([
            'message' => 'Sesi login berhasil di-logout.',
        ]);
    }

    public function destroyUser(Request $request, User $user): JsonResponse
    {
        $currentTokenId = $request->user()?->currentAccessToken()?->id;

        $deleted = $this->activeSessionQuery()
            ->where('tokenable_id', $user->id)
            ->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))
            ->delete();

        $this->clearLastSeenIfNoActiveSession($user);

        return response()->json([
            'message' => $deleted > 0
                ? "{$deleted} sesi login user berhasil di-logout."
                : 'Tidak ada sesi aktif yang dapat di-logout untuk user ini.',
        ]);
    }

    public function resetPassword(User $user): JsonResponse
    {
        $user->forceFill([
            'password' => Hash::make('12345678'),
            'must_change_password' => true,
            'password_changed_at' => null,
        ])->save();
        $user->tokens()->delete();

        return response()->json(['message' => 'Password user direset ke default 12345678.']);
    }

    public function resetPasswordLimit(User $user): JsonResponse
    {
        $user->forceFill(['password_changed_at' => null])->save();

        return response()->json(['message' => 'Batas ubah password sudah direset.']);
    }

    public function resetPhotoLimit(User $user): JsonResponse
    {
        $user->forceFill(['photo_changed_at' => null])->save();

        return response()->json(['message' => 'Batas ganti foto profil sudah direset.']);
    }

    private function activeSessionQuery()
    {
        return PersonalAccessToken::query()
            ->with(['tokenable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                User::class => ['karyawan'],
            ])])
            ->where('tokenable_type', User::class)
            ->whereIn('name', [self::PORTAL_TOKEN_NAME, self::MOBILE_TOKEN_NAME])
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(function ($query): void {
                $query
                    ->where(function ($web): void {
                        $this->applyIdleWindow($web, self::PORTAL_TOKEN_NAME, (int) config('sanctum.idle_expiration', 30));
                    })
                    ->orWhere(function ($mobile): void {
                        $this->applyIdleWindow($mobile, self::MOBILE_TOKEN_NAME, (int) config('sanctum.mobile_idle_expiration', 3 * 24 * 60));
                    });
            });
    }

    private function applyIdleWindow($query, string $tokenName, int $idleMinutes): void
    {
        $query->where('name', $tokenName);

        if ($idleMinutes <= 0) {
            return;
        }

        $idleCutoff = now()->subMinutes($idleMinutes);

        $query->where(function ($inner) use ($idleCutoff): void {
            $inner
                ->where('last_used_at', '>', $idleCutoff)
                ->orWhere(fn ($created) => $created
                    ->whereNull('last_used_at')
                    ->where('created_at', '>', $idleCutoff));
        });
    }

    private function userQuickList(string $keyword)
    {
        return User::query()
            ->with('karyawan:nik,nama_karyawan,jabatan,posisi,departement,divisi')
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('name', 'like', "%{$keyword}%")
                        ->orWhere('username', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhereHas('karyawan', function ($employeeQuery) use ($keyword): void {
                            $employeeQuery->where('nama_karyawan', 'like', "%{$keyword}%")
                                ->orWhere('nik', 'like', "%{$keyword}%")
                                ->orWhere('jabatan', 'like', "%{$keyword}%")
                                ->orWhere('posisi', 'like', "%{$keyword}%")
                                ->orWhere('departement', 'like', "%{$keyword}%")
                                ->orWhere('divisi', 'like', "%{$keyword}%");
                        });
                });
            })
            ->orderByDesc('last_seen_at')
            ->orderBy('level')
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (User $user): array => $this->serializeUser($user))
            ->values();
    }

    private function serializeSession(PersonalAccessToken $token, Request $request): array
    {
        $user = $token->tokenable instanceof User ? $token->tokenable : null;
        $employee = $user?->karyawan;
        $lastActiveAt = $token->last_used_at ?? $token->created_at;

        return [
            'id' => $token->id,
            'user_id' => $user?->id,
            'user_name' => $employee?->nama_karyawan ?: $user?->name,
            'username' => $employee?->nik ?: $user?->username,
            'level_label' => $this->levelLabel((int) ($user?->level ?? 0)),
            'position' => $employee?->jabatan ?: $employee?->posisi,
            'department' => $employee?->departement ?: $employee?->divisi,
            'platform' => $token->name === self::MOBILE_TOKEN_NAME ? 'mobile' : 'web',
            'platform_label' => $token->name === self::MOBILE_TOKEN_NAME ? 'Mobile' : 'HRIS Web',
            'device_name' => $token->device_name ?: 'Perangkat tidak teridentifikasi',
            'network_address' => $this->maskedIp($token->ip_address),
            'signed_in_at' => $token->created_at?->toIso8601String(),
            'last_active_at' => $lastActiveAt?->toIso8601String(),
            'is_current_session' => (int) ($request->user()?->currentAccessToken()?->id ?? 0) === (int) $token->id,
        ];
    }

    private function serializeEmployee(Karyawan $employee): array
    {
        return [
            'id' => $employee->id,
            'nik' => $employee->nik,
            'name' => $employee->nama_karyawan,
            'position' => $employee->jabatan ?: $employee->posisi,
            'department' => $employee->departement ?: $employee->divisi,
            'join_date' => $employee->join_date?->toDateString(),
        ];
    }

    private function serializeUser(User $user): array
    {
        $employee = $user->karyawan;

        return [
            'id' => $user->id,
            'name' => $employee?->nama_karyawan ?: $user->name,
            'username' => $employee?->nik ?: $user->username,
            'email' => $user->email,
            'level_label' => $this->levelLabel((int) $user->level),
            'is_active' => (bool) $user->is_active,
            'position' => $employee?->jabatan ?: $employee?->posisi,
            'department' => $employee?->departement ?: $employee?->divisi,
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            'photo_changed_at' => $user->photo_changed_at?->toIso8601String(),
            'password_changed_at' => $user->password_changed_at?->toIso8601String(),
            'must_change_password' => (bool) $user->must_change_password,
        ];
    }

    private function isManagedToken(PersonalAccessToken $token): bool
    {
        return $token->tokenable_type === User::class
            && in_array($token->name, [self::PORTAL_TOKEN_NAME, self::MOBILE_TOKEN_NAME], true);
    }

    private function clearLastSeenIfNoActiveSession(?User $user): void
    {
        if (! $user || $this->activeSessionQuery()->where('tokenable_id', $user->id)->exists()) {
            return;
        }

        $user->forceFill(['last_seen_at' => null])->save();
    }

    private function levelLabel(int $level): string
    {
        return match ($level) {
            0 => 'IT',
            1 => 'Admin',
            2 => 'HRD',
            3 => 'Karyawan',
            default => 'User',
        };
    }

    private function maskedIp(?string $ipAddress): ?string
    {
        if (! $ipAddress) {
            return null;
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $segments = explode('.', $ipAddress);

            return $segments[0].'.'.$segments[1].'.x.x';
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $segments = explode(':', $ipAddress);

            return implode(':', array_slice($segments, 0, 3)).':****';
        }

        return null;
    }
}
