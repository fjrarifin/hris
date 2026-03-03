<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends Controller
{
    public function show()
    {
        return view('auth.change-password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'password' => ['required', 'min:6', 'confirmed'],
        ]);

        $user = auth()->user();

        $user->password = Hash::make($request->password);
        $user->must_change_password = false;
        $user->save();

        // ✅ setelah ganti password, masuk dashboard sesuai role
        return ((int) $user->level === 0)
            ? redirect()->route('admin.dashboard')->with('success', 'Password berhasil diperbarui ✅')
            : redirect()->route('user.dashboard')->with('success', 'Password berhasil diperbarui ✅');
    }
}
