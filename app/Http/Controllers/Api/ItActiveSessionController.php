<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class ItActiveSessionController extends Controller
{
    private const PORTAL_TOKEN_NAME = 'hris-fe';

    private const MOBILE_TOKEN_NAME = 'hris-mobile';

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'platform' => ['nullable', Rule::in(['web', 'mobile'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $keyword = trim((string) ($validated['q'] ?? ''));
        $platform = $validated['platform'] ?? '';

        $sessions = $this->activeSessionQuery()
            ->when($platform === 'web', fn ($query) => $query->where('name', self::PORTAL_TOKEN_NAME))
            ->when($platform === 'mobile', fn ($query) => $query->where('name', self::MOBILE_TOKEN_NAME))
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->whereHasMorph('tokenable', [User::class], function ($userQuery) use ($keyword): void {
                    $userQuery->where(function ($inner) use ($keyword): void {
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
                });
            })
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 15), ['*'], 'page', (int) ($validated['page'] ?? 1));

        return response()->json([
            'records' => $sessions
                ->through(fn (PersonalAccessToken $token): array => $this->serializeSession($token, $request))
                ->items(),
            'pagination' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
                'from' => $sessions->firstItem(),
                'to' => $sessions->lastItem(),
            ],
            'summary' => $this->summary(),
            'filters' => [
                'q' => $keyword,
                'platform' => $platform,
            ],
        ]);
    }

    public function destroy(Request $request, PersonalAccessToken $token): JsonResponse
    {
        abort_unless($this->isManagedToken($token), 404);

        if ($this->isCurrentToken($request, $token)) {
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

    private function summary(): array
    {
        $baseQuery = $this->activeSessionQuery();

        return [
            'total' => (clone $baseQuery)->count(),
            'web' => (clone $baseQuery)->where('name', self::PORTAL_TOKEN_NAME)->count(),
            'mobile' => (clone $baseQuery)->where('name', self::MOBILE_TOKEN_NAME)->count(),
        ];
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
            'email' => $user?->email,
            'level' => (int) ($user?->level ?? 0),
            'level_label' => $this->levelLabel((int) ($user?->level ?? 0)),
            'position' => $employee?->jabatan ?: $employee?->posisi,
            'department' => $employee?->departement ?: $employee?->divisi,
            'platform' => $token->name === self::MOBILE_TOKEN_NAME ? 'mobile' : 'web',
            'platform_label' => $token->name === self::MOBILE_TOKEN_NAME ? 'Mobile' : 'HRIS Web',
            'device_name' => $token->device_name ?: 'Perangkat tidak teridentifikasi',
            'network_address' => $this->maskedIp($token->ip_address),
            'signed_in_at' => $token->created_at?->toIso8601String(),
            'last_active_at' => $lastActiveAt?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'is_current_session' => $this->isCurrentToken($request, $token),
        ];
    }

    private function isManagedToken(PersonalAccessToken $token): bool
    {
        return $token->tokenable_type === User::class
            && in_array($token->name, [self::PORTAL_TOKEN_NAME, self::MOBILE_TOKEN_NAME], true);
    }

    private function isCurrentToken(Request $request, PersonalAccessToken $token): bool
    {
        return (int) ($request->user()?->currentAccessToken()?->id ?? 0) === (int) $token->id;
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
