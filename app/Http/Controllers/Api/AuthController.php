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

class AuthController extends Controller
{
    private const PASSWORD_CHANGE_INTERVAL_DAYS = 30;

    public function __construct(private readonly FrontendNavigation $navigation) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('username', trim($credentials['username']))
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        $user->tokens()->where('name', 'hris-fe')->delete();
        $token = $user->createToken('hris-fe')->plainTextToken;

        return response()->json([
            'token' => $token,
            ...$this->sessionPayload($user),
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
            ? asset('storage/'.ltrim($path, '/'))
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
}
