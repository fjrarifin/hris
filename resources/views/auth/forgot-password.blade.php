@extends('layouts.auth')

@section('title', 'Lupa Password')

@section('content')
	<div class="flex min-h-screen items-center justify-center px-4">
		<div class="w-full max-w-md rounded-2xl border bg-white p-6 shadow-sm">

			<div class="mb-4">
				<div class="mx-auto flex w-48 items-center justify-center overflow-hidden rounded-2xl">
					<img src="{{ asset('hompimplay_icon.png') }}" alt="Logo Perusahaan" class="h-full w-full object-contain">
				</div>
			</div>

			<h2 class="text-center text-lg font-extrabold">Lupa Password</h2>
			<p class="mt-2 text-center text-sm text-gray-500">
				Masukkan username untuk menerima kode OTP.
			</p>

			<form method="POST" action="{{ route('password.send-otp') }}" class="mt-6 space-y-4">
				@csrf

				<div>
					<label class="text-sm font-semibold">Username</label>
					<input type="text" name="username"
						class="mt-1 w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" required>
					@error('username')
						<p class="mt-1 text-xs text-red-600">{{ $message }}</p>
					@enderror
				</div>

				<button id="btnSubmit" class="w-full rounded-xl bg-indigo-600 py-2.5 font-semibold text-white hover:bg-indigo-700">
					Kirim OTP
				</button>

			</form>

			{{-- LOADING MODAL --}}
			<div id="loadingModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">

				<div class="flex flex-col items-center rounded-2xl bg-white px-6 py-5 shadow-xl">
					{{-- Spinner --}}
					<div class="mb-3 h-10 w-10 animate-spin rounded-full border-4 border-indigo-200 border-t-indigo-600">
					</div>

					<p class="text-sm font-semibold text-gray-700">
						Sedang mengirim OTP...
					</p>
					<p class="mt-1 text-xs text-gray-500">
						Mohon tunggu sebentar
					</p>
				</div>
			</div>


			<div class="mt-4 text-center">
				<a href="{{ route('login') }}" class="text-xs text-gray-500 hover:text-indigo-600">
					Kembali ke Login
				</a>
			</div>

		</div>
	</div>

	<script>
		document.querySelector('form').addEventListener('submit', function() {
			document.getElementById('btnSubmit').disabled = true;
			document.getElementById('btnSubmit').innerText = 'Mengirim...';

			const modal = document.getElementById('loadingModal');
			modal.classList.remove('hidden');
			modal.classList.add('flex');
		});
	</script>

@endsection
