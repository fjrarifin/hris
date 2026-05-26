<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\FrontendNavigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    private const PASSWORD_CHANGE_INTERVAL_DAYS = 30;

    private const PORTAL_TOKEN_NAME = 'hris-fe';

    public function __construct(private readonly FrontendNavigation $navigation) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $result = DB::transaction(function () use ($credentials, $request): array {
            $user = User::query()
                ->where('username', trim($credentials['username']))
                ->lockForUpdate()
                ->first();

            if (! $user || ! Hash::check($credentials['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'username' => ['Username atau password salah.'],
                ]);
            }

            $activeToken = $user->tokens()
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderByDesc('last_used_at')
                ->orderByDesc('created_at')
                ->first();

            if ($activeToken) {
                return [
                    'active_session' => $this->serializeActiveSession($activeToken),
                ];
            }

            $token = $user->createToken(self::PORTAL_TOKEN_NAME);
            $token->accessToken->forceFill([
                'device_name' => $this->deviceName($request->userAgent()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])->save();

            return [
                'user' => $user,
                'token' => $token->plainTextToken,
            ];
        });

        if (isset($result['active_session'])) {
            return response()->json([
                'code' => 'ACTIVE_SESSION_EXISTS',
                'message' => 'Akun ini sedang login pada perangkat lain.',
                'active_session' => $result['active_session'],
            ], 409);
        }

        return response()->json([
            'token' => $result['token'],
            ...$this->sessionPayload($result['user']),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->sessionPayload($request->user()));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($request, $validated): User {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $availability = $this->passwordChangeAvailability($user);

            if (! $availability['can_change_password']) {
                throw ValidationException::withMessages([
                    'password' => [
                        'Password hanya dapat diganti 1 kali dalam 30 hari. Anda dapat mengganti kembali pada '.$availability['password_change_available_label'].'.',
                    ],
                ]);
            }

            if (! Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Password saat ini tidak sesuai.'],
                ]);
            }

            $user->update([
                'password' => Hash::make($validated['password']),
                'must_change_password' => false,
                'password_changed_at' => now(),
            ]);

            return $user->fresh();
        });

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
            ...$this->sessionPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    private function sessionPayload(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'level' => (int) $user->level,
                'level_label' => $this->navigation->levelLabel($user),
                'photo' => $user->photo,
                'photo_url' => $this->publicFileUrl($user->photo),
                'must_change_password' => (bool) $user->must_change_password,
                ...$this->passwordChangeAvailability($user),
            ],
            'dashboard_path' => $this->navigation->dashboardPath($user),
            'menus' => $this->navigation->menusFor($user),
        ];
    }

    private function publicFileUrl(?string $path): ?string
    {
        return $path
            ? route('profile-photos.show', ['filename' => basename($path)])
            : null;
    }

    private function passwordChangeAvailability(User $user): array
    {
        $availableAt = $user->password_changed_at?->copy()->addDays(self::PASSWORD_CHANGE_INTERVAL_DAYS);

        return [
            'password_changed_at' => $user->password_changed_at?->toIso8601String(),
            'password_change_available_at' => $availableAt?->toIso8601String(),
            'password_change_available_label' => $availableAt?->format('d/m/Y H:i').' WIB',
            'can_change_password' => (bool) $user->must_change_password
                || ! $availableAt
                || now()->greaterThanOrEqualTo($availableAt),
        ];
    }

    private function serializeActiveSession(PersonalAccessToken $token): array
    {
        return [
            'device_name' => $token->device_name ?: 'Perangkat tidak teridentifikasi',
            'network_address' => $this->maskedIp($token->ip_address),
            'signed_in_at' => $token->created_at?->toIso8601String(),
            'last_active_at' => ($token->last_used_at ?? $token->created_at)?->toIso8601String(),
        ];
    }

    private function deviceName(?string $userAgent): string
    {
        $userAgent ??= '';

        $platform = match (true) {
            preg_match('/Android/i', $userAgent) === 1 => 'Android',
            preg_match('/iPhone|iPad/i', $userAgent) === 1 => 'iPhone/iPad',
            preg_match('/Windows/i', $userAgent) === 1 => 'Windows',
            preg_match('/Macintosh|Mac OS X/i', $userAgent) === 1 => 'macOS',
            preg_match('/Linux/i', $userAgent) === 1 => 'Linux',
            default => 'perangkat tidak dikenal',
        };

        $browser = match (true) {
            preg_match('/Edg\//i', $userAgent) === 1 => 'Microsoft Edge',
            preg_match('/OPR\/|Opera/i', $userAgent) === 1 => 'Opera',
            preg_match('/Firefox\//i', $userAgent) === 1 => 'Firefox',
            preg_match('/Chrome\//i', $userAgent) === 1 => 'Chrome',
            preg_match('/Safari\//i', $userAgent) === 1 => 'Safari',
            default => 'Browser tidak dikenal',
        };

        return "{$browser} di {$platform}";
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
