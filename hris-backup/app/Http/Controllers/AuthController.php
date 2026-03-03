<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'nik' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $nik = trim($request->nik);
        $password = $request->password;

        // ✅ 1) Cek dulu di tabel users (login normal)
        $user = User::where('nik', $nik)->first();

        if ($user) {
            if (Auth::attempt(['nik' => $nik, 'password' => $password])) {
                $request->session()->regenerate();

                // ✅ kalau masih wajib ganti password
                if (auth()->user()->must_change_password) {
                    return redirect()->route('password.change');
                }

                return $this->redirectByLevel();
            }

            return back()->withErrors([
                'nik' => 'NIK atau password salah.',
            ])->withInput();
        }

        // ✅ 2) Kalau belum ada di users → cek di m_karyawan
        $karyawan = DB::table('m_karyawan')->where('nik', $nik)->first();

        if (! $karyawan) {
            return back()->withErrors([
                'nik' => 'NIK tidak terdaftar.',
            ])->withInput();
        }

        // ✅ 3) Password default = "password"
        if ($password !== 'password') {
            return back()->withErrors([
                'password' => 'Password default untuk login pertama adalah: password',
            ])->withInput();
        }

        // ✅ 4) Auto insert ke users
        $newUser = User::create([
            'nik' => $karyawan->nik,
            'name' => $karyawan->nama_karyawan,
            'email' => $karyawan->nik.'@hris.local',
            'password' => Hash::make('password'),
            'level' => 1,
            'must_change_password' => true, // ✅ WAJIB
        ]);

        // ✅ 5) Auto login setelah insert
        Auth::login($newUser);
        $request->session()->regenerate();

        // ✅ paksa ubah password
        return redirect()->route('password.change');
    }

    private function redirectByLevel()
    {
        return ((int) auth()->user()->level === 0)
            ? redirect()->route('admin.dashboard')
            : redirect()->route('user.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
