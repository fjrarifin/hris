<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Notifications\IncompleteProfileNotification;

class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user()->load('karyawan');

        return view('staff.profile.index', compact('user'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'no_hp' => 'required|string|max:20',
            'email' => 'required|email',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $user = Auth::user();

        // update email
        $user->update([
            'email' => $request->email
        ]);

        // update no hp
        $user->karyawan->update([
            'no_hp' => $request->no_hp
        ]);

        // upload photo
        if ($request->hasFile('photo')) {

            $path = $request->file('photo')->store('profile-photos', 'public');

            $user->update([
                'photo' => $path
            ]);
        }

        return back()->with('success', 'Data berhasil diperbarui');
    }
}
