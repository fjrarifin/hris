<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PasswordResetOtp;
use App\Http\Services\EmailService;
use App\Http\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PasswordController extends Controller
{
    protected $emailService;
    protected $whatsAppService;

    public function __construct(EmailService $emailService, WhatsAppService $whatsAppService)
    {
        $this->emailService = $emailService;
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Tampilkan form change password (for must_change_password)
     */
    public function change()
    {
        return view('auth.change-password');
    }

    /**
     * Update password (for must_change_password)
     */
    public function update(Request $request)
    {
        $request->validate([
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $user = Auth::user();

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Password berhasil diperbarui');
    }

    /**
     * Tampilkan form request OTP forget password
     */
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send OTP ke email dan whatsapp user
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'username' => ['required', 'exists:users,username'],
        ], [
            'username.exists' => 'Username tidak ditemukan',
        ]);

        try {
            $user = User::where('username', $request->username)->firstOrFail();

            // Generate OTP 6 digit
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Hapus OTP lama
            PasswordResetOtp::where('email', $user->email)
                ->where('is_used', false)
                ->delete();

            // Simpan OTP baru
            PasswordResetOtp::create([
                'email'      => $user->email,
                'otp'        => $otp,
                'expired_at' => now()->addMinutes(30),
            ]);

            /** =====================
             *  KIRIM WHATSAPP
             *  ===================== */
            $karyawan = $user->karyawan;

            if (!$karyawan || !$karyawan->no_hp) {
                return back()->with('error', 'Nomor HP tidak ditemukan pada profil Anda.');
            }

            $phone = preg_replace('/[^0-9]/', '', $karyawan->no_hp);

            if (str_starts_with($phone, '0')) {
                $phone = '62' . substr($phone, 1);
            } elseif (!str_starts_with($phone, '62')) {
                $phone = '62' . $phone;
            }

            $waMessage = $this->buildOtpWhatsappMessage($user->name, $otp);

            Log::info('OTP WA - mulai kirim', [
                'phone' => $phone,
                'otp'   => $otp,
            ]);

            $waSent = $this->whatsAppService->sendMessage(
                $phone,
                $waMessage
            );

            if (! $waSent) {
                Log::warning('OTP WA gagal dikirim');
            }

            // /** =====================
            //  *  KIRIM EMAIL
            //  *  ===================== */
            // Log::info('OTP Email - mulai kirim', [
            //     'email' => $user->email,
            // ]);

            // $emailBody = $this->getOtpEmailTemplate($user->name, $otp);

            // $this->emailService->send(
            //     $user->email,
            //     'Kode OTP Reset Password Anda',
            //     $emailBody
            // );

            return redirect()->route('password.verify-otp')
                ->with('success', 'Kode OTP telah dikirim ke Email dan WhatsApp Anda')
                ->with('email', $user->email);
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->with('error', 'Gagal mengirim OTP. Silakan coba lagi.')
                ->withInput();
        }
    }

    /**
     * Tampilkan form verifikasi OTP
     */
    public function showVerifyOtpForm()
    {
        $email = session('email');
        if (!$email) {
            return redirect()->route('password.forgot');
        }

        return view('auth.verify-otp', compact('email'));
    }

    /**
     * Verifikasi OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'otp' => ['required', 'digits:6'],
        ]);

        try {
            $passwordResetOtp = PasswordResetOtp::where('email', $request->email)
                ->where('otp', $request->otp)
                ->first();

            if (!$passwordResetOtp) {
                return back()->with('error', 'Kode OTP tidak valid');
            }

            if (!$passwordResetOtp->isValid()) {
                return back()->with('error', 'Kode OTP telah kadaluarsa. Silakan minta OTP baru.');
            }

            session([
                'otp_verified' => true,
                'reset_email' => $request->email,
            ]);

            return redirect()->route('password.reset-form');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan. Silakan coba lagi.');
        }
    }

    /**
     * Tampilkan form reset password
     */
    public function showResetForm()
    {
        if (!session('otp_verified') || !session('reset_email')) {
            return redirect()->route('password.forgot');
        }

        $email = session('reset_email');

        return view('auth.reset-password', compact('email'));
    }

    /**
     * Reset password dengan OTP yang sudah diverifikasi
     */
    public function resetPassword(Request $request)
    {
        if (!session('otp_verified')) {
            return redirect()->route('password.forgot');
        }

        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        try {
            $email = session('reset_email');

            $user = User::where('email', $email)->firstOrFail();

            $user->password = Hash::make($request->password);
            $user->must_change_password = false;
            $user->save();

            // Mark OTP sebagai used
            PasswordResetOtp::where('email', $email)
                ->where('is_used', false)
                ->update(['is_used' => true]);

            // 🔥🔥🔥 CLEAR SESSION OTP
            session()->forget([
                'otp_verified',
                'reset_email',
            ]);

            return redirect()->route('login')->with('success', 'Password berhasil direset. Silakan login dengan password baru Anda.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mereset password. Silakan coba lagi.');
        }
    }

    /**
     * Generate HTML email template untuk OTP
     */
    private function getOtpEmailTemplate($userName, $otp)
    {
        return <<<HTML
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                <!-- Header -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white;">
                    <h2 style="margin: 0;">Reset Password</h2>
                </div>

                <!-- Content -->
                <div style="padding: 30px;">
                    <p>Halo <strong>$userName</strong>,</p>

                    <p>Kami menerima permintaan untuk mereset password akun Anda. Gunakan kode OTP berikut untuk melanjutkan:</p>

                    <!-- OTP Box -->
                    <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0;">
                        <p style="margin: 0 0 10px 0; color: #666; font-size: 14px;">Kode OTP Anda:</p>
                        <div style="font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #667eea;">
                            $otp
                        </div>
                    </div>

                    <p style="color: #e74c3c; font-weight: bold;">⏱️ Kode OTP ini berlaku selama 30 menit</p>

                    <p style="color: #7f8c8d; font-size: 14px;">
                        Jika Anda tidak melakukan permintaan ini, abaikan email ini. Akun Anda akan tetap aman.
                    </p>
                </div>

                <!-- Footer -->
                <div style="background: #f9f9f9; padding: 15px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0;">
                    <p style="margin: 0;">© 2026 HRGA Information System. All rights reserved.</p>
                </div>
            </div>
        </div>
        HTML;
    }

    private function buildOtpWhatsappMessage(string $name, string $otp): string
    {
        return "🔐 *Reset Password HRGA*\n\n"
            . "Halo *{$name}*,\n\n"
            . "Kode OTP Anda adalah:\n"
            . "*{$otp}*\n\n"
            . "⏱ Berlaku selama 30 menit.\n"
            . "Jangan bagikan kode ini kepada siapa pun.\n\n"
            . "— IT Hompim Play";
    }
}
