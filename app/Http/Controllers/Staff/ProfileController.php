<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user()->load('karyawan');

        return view('staff.profile.index', compact('user'));
    }

    public function password()
    {
        return view('staff.profile.password');
    }

    public function update(Request $request)
    {
        $user = Auth::user()->load('karyawan');
        $karyawan = $user->karyawan;

        $request->validate([
            'no_hp' => 'required|string|max:20',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $newPhone = trim((string) $request->no_hp);
        $newEmail = strtolower(trim((string) $request->email));
        $currentPhone = trim((string) ($karyawan->no_hp ?? ''));
        $currentEmail = strtolower(trim((string) ($user->email ?? '')));
        $phoneChanged = $newPhone !== $currentPhone;
        $emailChanged = $newEmail !== $currentEmail;

        if ($phoneChanged && $karyawan->phone_updated_at) {
            return back()->withErrors([
                'no_hp' => 'Nomor telepon hanya bisa diganti 1 kali. Kesempatan perubahan sudah digunakan.'
            ])->withInput();
        }

        if ($emailChanged && $user->email_updated_at) {
            return back()->withErrors([
                'email' => 'Email hanya bisa diganti 1 kali. Kesempatan perubahan sudah digunakan.'
            ])->withInput();
        }

        if ($emailChanged) {
            $user->update([
                'email' => $newEmail,
                'email_updated_at' => now(),
            ]);

            $karyawan->update([
                'email' => $newEmail,
            ]);
        }

        if ($phoneChanged) {
            $karyawan->update([
                'no_hp' => $newPhone,
                'phone_updated_at' => now(),
            ]);
        }

        // upload photo
        if ($request->hasFile('photo')) {

            $path = $request->file('photo')->store('profile-photos', 'public');

            $user->update([
                'photo' => $path
            ]);
        }

        $message = $phoneChanged || $emailChanged
            ? 'Data kontak berhasil diperbarui. Kesempatan perubahan untuk field yang diganti sudah terpakai.'
            : 'Data berhasil diperbarui';

        return back()->with('success', $message);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        return redirect()
            ->route('staff.profile.index')
            ->with('success', 'Password berhasil diperbarui');
    }
}
