@extends('layouts.auth')

@section('title', 'Login')

@section('content')
	<div class="flex min-h-screen items-center justify-center px-4 py-8">
		<div class="w-full max-w-md">
			{{-- Card Login --}}
			<div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-lg">

				{{-- Logo --}}
				<div class="mb-4">
					<div class="mx-auto flex w-48 items-center justify-center overflow-hidden rounded-2xl">
						<img src="{{ asset('hompimplay_icon.png') }}" alt="Logo Perusahaan" class="h-full w-full object-contain">
					</div>
				</div>

				{{-- Header Text --}}
				<div class="mb-6 text-center">
					<h1 class="text-2xl font-bold text-gray-900">Halo Selamat Datang Kembali</h1>
					<p class="mt-2 text-sm text-gray-600">Masuk menggunakan NIK karyawan Anda</p>
				</div>

				{{-- Error Messages --}}
				@if ($errors->any())
					<div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-2">
						<div class="flex items-start gap-3">
							<svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600" fill="currentColor" viewBox="0 0 20 20">
								<path fill-rule="evenodd"
									d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
									clip-rule="evenodd" />
							</svg>
							<div class="flex-1">
								<ul class="space-y-1 text-sm text-red-700">
									@foreach ($errors->all() as $error)
										<li>{{ $error }}</li>
									@endforeach
								</ul>
							</div>
						</div>
					</div>
				@endif

				{{-- Success Message (if any) --}}
				@if (session('success'))
					<div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4">
						<div class="flex items-start gap-3">
							<svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600" fill="currentColor" viewBox="0 0 20 20">
								<path fill-rule="evenodd"
									d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
									clip-rule="evenodd" />
							</svg>
							<p class="text-sm text-green-700">{{ session('success') }}</p>
						</div>
					</div>
				@endif

				{{-- Login Form --}}
				<form method="POST" action="{{ route('login') }}" class="space-y-5">
					@csrf

					{{-- NIK Field --}}
					<div>
						<label for="username" class="block text-sm font-medium text-gray-700">
							NIK <span class="text-red-500">*</span>
						</label>
						<div class="relative mt-2">
							<div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
								<svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
										d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
								</svg>
							</div>
							<input type="text" id="username" name="username" value="{{ old('username') }}"
								placeholder="Contoh: HPP12345678"
								class="@error('username') border-red-300 @enderror w-full rounded-xl border-gray-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500"
								required autofocus>
						</div>
						@error('username')
							<p class="mt-1 text-xs text-red-600">{{ $message }}</p>
						@enderror
					</div>

					{{-- Password Field --}}
					<div>
						<label for="password" class="block text-sm font-medium text-gray-700">
							Password <span class="text-red-500">*</span>
						</label>
						<div class="relative mt-2">
							<div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
								<svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
										d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
								</svg>
							</div>
							<input type="password" id="password" name="password" placeholder="Masukkan password Anda"
								class="@error('password') border-red-300 @enderror w-full rounded-xl border-gray-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500"
								required>
						</div>
						@error('password')
							<p class="mt-1 text-xs text-red-600">{{ $message }}</p>
						@enderror
					</div>

					{{-- Remember Me & Forgot Password --}}
					<div class="flex items-center justify-between">
						{{-- <div class="flex items-center">
							<input type="checkbox" id="remember" name="remember"
								class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
								{{ old('remember') ? 'checked' : '' }}>
							<label for="remember" class="ml-2 text-sm text-gray-700">
								Ingat saya
							</label>
						</div> --}}

						<a href="{{ route('password.forgot') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
							Lupa password?
						</a>
					</div>

					{{-- Submit Button --}}
					<button type="submit"
						class="w-full rounded-xl bg-indigo-600 py-2 font-semibold text-white shadow-sm transition duration-200 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
						Masuk
					</button>
				</form>

				{{-- Footer --}}
				<div class="mt-6 text-center">
					<p class="text-xs text-gray-500">
						Butuh bantuan? Hubungi
						<a href="https://wa.me/6282117289833" class="font-medium text-indigo-600 hover:text-indigo-500">
							IT Department
						</a>
					</p>
				</div>

			</div>

			{{-- Copyright --}}
			<div class="mt-6 text-center">
				<p class="text-xs text-gray-500">
					© {{ date('Y') }} HR Information System. All rights reserved.
				</p>
			</div>

		</div>
	</div>
@endsection
