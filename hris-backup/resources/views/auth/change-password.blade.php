@extends('layouts.guest')

@section('content')
<div class="min-h-screen flex items-center justify-center px-4 bg-gray-50">
    <div class="w-full max-w-md bg-white border border-gray-200 rounded-3xl shadow-sm p-6">
        <h1 class="text-xl font-extrabold text-gray-900">Ubah Password</h1>
        <p class="text-sm text-gray-600 mt-1">
            Demi keamanan akun, kamu wajib mengganti password sebelum masuk sistem.
        </p>

        <form method="POST" action="{{ route('password.update') }}" class="mt-5 space-y-4">
            @csrf

            <div>
                <label class="text-sm font-semibold text-gray-700">Password Baru</label>
                <input
                    type="password"
                    name="password"
                    class="mt-1 w-full rounded-xl border-gray-200 focus:border-indigo-500 focus:ring-indigo-500"
                    required
                >
                @error('password')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="text-sm font-semibold text-gray-700">Konfirmasi Password</label>
                <input
                    type="password"
                    name="password_confirmation"
                    class="mt-1 w-full rounded-xl border-gray-200 focus:border-indigo-500 focus:ring-indigo-500"
                    required
                >
            </div>

            <button
                type="submit"
                class="w-full py-2.5 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition"
            >
                Simpan Password ✅
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button type="submit" class="w-full py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-sm font-semibold transition">
                Logout
            </button>
        </form>
    </div>
</div>
@endsection
