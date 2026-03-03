@extends('layouts.auth')

@section('title', 'Verifikasi OTP')

@section('content')
	<div class="flex min-h-screen items-center justify-center px-4">
		<div class="w-full max-w-md rounded-2xl border bg-white p-6 shadow-sm">

			<div class="mb-4">
				<div class="mx-auto flex w-48 items-center justify-center overflow-hidden rounded-2xl">
					<img src="{{ asset('hompimplay_icon.png') }}" alt="Logo Perusahaan" class="h-full w-full object-contain">
				</div>
			</div>

			<h2 class="text-center text-lg font-extrabold">Verifikasi OTP</h2>
			<p class="mt-2 text-center text-sm text-gray-500">
				Masukkan kode OTP yang dikirim ke email / whatsapp Anda.
			</p>

			<form method="POST" action="{{ route('password.verify-otp-post') }}" class="mt-6 space-y-4">
				@csrf

				<input type="hidden" name="email" value="{{ $email }}">

				<div>
					<label class="text-sm font-semibold">Kode OTP</label>
					<input type="text" name="otp" maxlength="6"
						class="mt-1 w-full rounded-xl border-gray-300 text-center text-lg tracking-widest focus:border-indigo-500 focus:ring-indigo-500"
						required>
					@error('otp')
						<p class="mt-1 text-xs text-red-600">{{ $message }}</p>
					@enderror
				</div>

				<button class="w-full rounded-xl bg-indigo-600 py-2.5 font-semibold text-white hover:bg-indigo-700">
					Verifikasi
				</button>
			</form>

		</div>
	</div>
@endsection
