<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\WhatsAppService;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class ForgotPasswordController extends Controller
{
    public function __construct(private readonly WhatsAppService $whatsAppService) {}

    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nik' => ['required', 'string', 'max:30'],
        ]);

        $user = $this->userForNik($validated['nik']);
        $phone = trim((string) $user->karyawan?->no_hp);

        if ($phone === '') {
            throw ValidationException::withMessages([
                'nik' => ['Nomor telepon karyawan belum tersedia. Hubungi HR untuk memperbarui profil.'],
            ]);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PasswordResetOtp::query()
            ->where('email', $user->email)
            ->where('is_used', false)
            ->delete();

        $passwordResetOtp = PasswordResetOtp::query()->create([
            'email' => $user->email,
            'otp' => $otp,
            'expired_at' => now()->addMinutes(2),
        ]);

        try {
            $sent = $this->whatsAppService->sendMessage(
                $phone,
                $this->otpMessage($user->name, $otp)
            );
        } catch (Throwable $exception) {
            report($exception);
            $sent = false;
        }

        if (! $sent) {
            $passwordResetOtp->delete();

            throw ValidationException::withMessages([
                'nik' => ['Kode OTP gagal dikirim. Silakan coba kembali.'],
            ]);
        }

        return response()->json([
            'message' => 'Kode OTP telah dikirim ke nomor telepon terdaftar.',
            'expires_in' => 120,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nik' => ['required', 'string', 'max:30'],
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $this->userForNik($validated['nik']);

        if (! $this->validOtp($user, $validated['otp'])) {
            throw ValidationException::withMessages([
                'otp' => ['Kode OTP salah atau sudah kedaluwarsa.'],
            ]);
        }

        return response()->json([
            'message' => 'Kode OTP berhasil diverifikasi.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nik' => ['required', 'string', 'max:30'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
        ], [
            'password.letters' => 'Password harus memiliki huruf.',
            'password.numbers' => 'Password harus memiliki angka.',
        ]);

        $user = $this->userForNik($validated['nik']);

        DB::transaction(function () use ($user, $validated): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            $passwordResetOtp = PasswordResetOtp::query()
                ->where('email', $user->email)
                ->where('otp', $validated['otp'])
                ->where('is_used', false)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $passwordResetOtp || ! $passwordResetOtp->isValid()) {
                throw ValidationException::withMessages([
                    'otp' => ['Kode OTP salah atau sudah kedaluwarsa.'],
                ]);
            }

            $user->update([
                'password' => Hash::make($validated['password']),
                'must_change_password' => false,
                'password_changed_at' => now(),
            ]);

            $user->tokens()->delete();
            $passwordResetOtp->markAsUsed();
        });

        return response()->json([
            'message' => 'password kamu sudah diganti, silahkan login menggunakan password terbaru',
        ]);
    }

    private function userForNik(string $nik): User
    {
        $user = User::query()
            ->with('karyawan')
            ->where('username', trim($nik))
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'nik' => ['NIK karyawan tidak ditemukan.'],
            ]);
        }

        return $user;
    }

    private function validOtp(User $user, string $otp): bool
    {
        $passwordResetOtp = PasswordResetOtp::query()
            ->where('email', $user->email)
            ->where('otp', $otp)
            ->where('is_used', false)
            ->latest('id')
            ->first();

        return $passwordResetOtp?->isValid() ?? false;
    }

    private function otpMessage(string $name, string $otp): string
    {
        return "*Reset Password HRIS*\n\n"
            ."Halo *{$name}*,\n\n"
            ."Kode OTP Anda adalah:\n"
            ."*{$otp}*\n\n"
            ."Kode ini berlaku selama 2 menit.\n"
            ."Jangan bagikan kode ini kepada siapa pun.\n\n"
            .'IT Hompim Play';
    }
}
