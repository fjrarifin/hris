<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Notifications\IncompleteProfileNotification;

class AuthController extends Controller
{
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

        $nik = trim($request->username);
        $password = $request->password;

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ CEK USER SUDAH ADA
        |--------------------------------------------------------------------------
        */
        $user = User::where('username', $nik)->first();

        if ($user) {

            if (!Auth::attempt(['username' => $nik, 'password' => $password])) {
                return back()->withErrors([
                    'username' => 'NIK atau password salah'
                ]);
            }

            $request->session()->regenerate();

            // 🔥 Cek profil tidak lengkap
            $this->notifyIncompleteProfile($user);

            // 🔐 Force change password jika wajib
            if ($user->must_change_password) {
                return redirect()->route('password.change');
            }

            return redirect()->route('dashboard');
        }

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ CEK DI TABEL m_karyawan (LOGIN PERTAMA)
        |--------------------------------------------------------------------------
        */
        $karyawan = DB::table('m_karyawan')
            ->where('nik', $nik)
            ->first();

        if (!$karyawan) {
            return back()->withErrors([
                'username' => 'NIK tidak terdaftar'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ PASSWORD DEFAULT LOGIN PERTAMA
        |--------------------------------------------------------------------------
        */
        if ($password !== 'password') {
            return back()->withErrors([
                'password' => 'Password default login pertama adalah: password'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 4️⃣ BUAT USER BARU
        |--------------------------------------------------------------------------
        */
        $newUser = User::create([
            'username' => $karyawan->nik,
            'name' => $karyawan->nama_karyawan,
            'email' => $karyawan->nik . '@hris.local',
            'password' => Hash::make('password'),
            'level' => 3, // STAFF
            'must_change_password' => true,
        ]);

        Auth::login($newUser);
        $request->session()->regenerate();

        // 🔥 Cek profil tidak lengkap
        $this->notifyIncompleteProfile($newUser);

        return redirect()->route('password.change');
    }

    /*
    |--------------------------------------------------------------------------
    | 🔔 NOTIF PROFILE TIDAK LENGKAP
    |--------------------------------------------------------------------------
    */
    private function notifyIncompleteProfile(User $user)
    {
        if (
            empty($user->email) ||
            empty(optional($user->karyawan)->no_hp)
        ) {

            $alreadyNotified = $user->notifications()
                ->where('type', IncompleteProfileNotification::class)
                ->exists();

            if (!$alreadyNotified) {
                $user->notify(new IncompleteProfileNotification());
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
