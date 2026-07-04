<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\IncompleteProfileNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    private const DEFAULT_FIRST_LOGIN_PASSWORD = '12345678';

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);

        $nik = $this->normalizeLoginIdentifier($request->username);
        $password = $request->password;

        $user = User::where('username', $nik)->first();

        if (! $user) {
            $user = $this->provisionEmployeeAccount($nik, $password);

            if (! $user && $this->findEmployeeForFirstLogin($nik)) {
                return back()->withErrors([
                    'password' => 'Password default login pertama adalah: '.self::DEFAULT_FIRST_LOGIN_PASSWORD,
                ]);
            }
        }

        if (! $user) {
            return back()->withErrors([
                'username' => 'NIK tidak terdaftar',
            ]);
        }

        return $this->loginUser($request, $user, $password);
    }

    private function provisionEmployeeAccount(string $identifier, string $password): ?User
    {
        $karyawan = $this->findEmployeeForFirstLogin($identifier);

        if (! $karyawan || $password !== self::DEFAULT_FIRST_LOGIN_PASSWORD) {
            return null;
        }

        $employeeNik = $this->normalizeLoginIdentifier($karyawan->nik);
        $existingUser = User::query()->where('username', $employeeNik)->first();

        if ($existingUser) {
            return $existingUser;
        }

        return User::create([
            'username' => $employeeNik,
            'name' => $karyawan->nama_karyawan,
            'email' => $this->uniqueEmployeeEmail($karyawan, $employeeNik),
            'password' => Hash::make(self::DEFAULT_FIRST_LOGIN_PASSWORD),
            'level' => 3,
            'must_change_password' => true,
            'is_active' => true,
        ]);
    }

    private function findEmployeeForFirstLogin(string $identifier): ?object
    {
        return DB::table('m_karyawan')
            ->where(function ($query) use ($identifier): void {
                $query
                    ->where('nik', $identifier)
                    ->orWhereRaw('TRIM(nik) = ?', [$identifier]);

                if (Schema::hasColumn('m_karyawan', 'pin')) {
                    $query
                        ->orWhere('pin', $identifier)
                        ->orWhereRaw('TRIM(pin) = ?', [$identifier]);
                }
            })
            ->first();
    }

    private function uniqueEmployeeEmail(object $karyawan, string $employeeNik): string
    {
        $email = filled($karyawan->email ?? null)
            ? trim((string) $karyawan->email)
            : "{$employeeNik}@hris.local";

        if (! User::query()->where('email', $email)->exists()) {
            return $email;
        }

        $fallbackEmail = "{$employeeNik}@hris.local";
        if (! User::query()->where('email', $fallbackEmail)->exists()) {
            return $fallbackEmail;
        }

        return "{$employeeNik}+".now()->format('YmdHis').'@hris.local';
    }

    private function loginUser(Request $request, User $user, string $password)
    {
        if (! $user->is_active) {
            return back()->withErrors([
                'username' => 'Akun ini sedang dinonaktifkan. Hubungi IT.',
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            return back()->withErrors([
                'username' => 'NIK atau password salah',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $this->notifyIncompleteProfile($user);

        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->route('dashboard');
    }

    private function notifyIncompleteProfile(User $user): void
    {
        if (
            empty($user->email) ||
            empty(optional($user->karyawan)->no_hp)
        ) {
            $alreadyNotified = $user->notifications()
                ->where('type', IncompleteProfileNotification::class)
                ->exists();

            if (! $alreadyNotified) {
                $user->notify(new IncompleteProfileNotification);
            }
        }
    }

    private function normalizeLoginIdentifier(string $identifier): string
    {
        return trim($identifier);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
