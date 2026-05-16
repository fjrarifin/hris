@extends('layouts.auth')

@section('title', 'Ubah Password')

@section('content')
	<div class="flex min-h-screen items-center justify-center px-4">

		<div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-lg">

			<div class="mb-2 text-center">
				<div class="mx-auto mb-4 flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100">
					<svg class="h-8 w-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
							d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
					</svg>
				</div>
				<h2 class="text-2xl font-bold text-gray-900">
					Ubah Password
				</h2>
				<p class="mt-2 text-sm text-gray-600">
					Demi keamanan akun, silakan ganti password default Anda.
				</p>
			</div>

			{{-- Error Messages --}}
			@if ($errors->any())
				<div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4">
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

			<form method="POST" action="{{ route('password.update') }}" id="changePasswordForm" class="space-y-5">
				@csrf

				{{-- Password Baru --}}
				<div>
					<label for="password" class="block text-sm font-semibold text-gray-700">
						Password Baru <span class="text-red-500">*</span>
					</label>
					<div class="relative mt-2">
						<div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
							<svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
							</svg>
						</div>
						<input type="password" id="password" name="password" placeholder="Minimal 8 karakter, 1 angka"
							class="w-full rounded-xl border-gray-300 pl-10 pr-10 focus:border-indigo-500 focus:ring-indigo-500" required>
						<button type="button" id="togglePassword"
							class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
							<svg id="eyeIcon" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
							</svg>
						</button>
					</div>

					{{-- Password Strength Indicator --}}
					<div class="mt-2">
						<div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
							<div id="passwordStrength" class="h-full w-0 rounded-full transition-all duration-300"></div>
						</div>
						<p id="passwordStrengthText" class="mt-1 text-xs text-gray-500"></p>
					</div>

					{{-- Password Requirements --}}
					<div class="mt-3 space-y-1 text-xs">
						<div id="requirement-length" class="flex items-center gap-2 text-gray-500">
							<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
							</svg>
							<span>Minimal 8 karakter</span>
						</div>
						<div id="requirement-number" class="flex items-center gap-2 text-gray-500">
							<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
							</svg>
							<span>Minimal 1 angka</span>
						</div>
						<div id="requirement-not-password" class="flex items-center gap-2 text-gray-500">
							<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
							</svg>
							<span>Tidak boleh menggunakan kata "password"</span>
						</div>
					</div>
				</div>

				{{-- Konfirmasi Password --}}
				<div>
					<label for="password_confirmation" class="block text-sm font-semibold text-gray-700">
						Konfirmasi Password <span class="text-red-500">*</span>
					</label>
					<div class="relative mt-2">
						<div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
							<svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
							</svg>
						</div>
						<input type="password" id="password_confirmation" name="password_confirmation"
							placeholder="Ketik ulang password baru"
							class="w-full rounded-xl border-gray-300 pl-10 pr-10 focus:border-indigo-500 focus:ring-indigo-500" required>
						<button type="button" id="togglePasswordConfirm"
							class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
							<svg id="eyeIconConfirm" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
							</svg>
						</button>
					</div>
					<p id="passwordMatchText" class="mt-1 text-xs text-gray-500"></p>
				</div>

				<button type="submit"
					class="w-full rounded-xl bg-indigo-600 py-2 font-semibold text-white shadow-sm transition duration-200 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
					Simpan Password
				</button>

			</form>

			<div class="mt-2 border-t pt-2">
				<form method="POST" action="{{ route('logout') }}" class="text-center">
					@csrf
					<button type="submit" class="inline-flex items-center gap-2 text-sm text-gray-600 transition hover:text-red-600">
						<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
								d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
						</svg>
						<span>Keluar</span>
					</button>
				</form>
			</div>

		</div>

	</div>

	{{-- JavaScript for Password Validation --}}
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const form = document.getElementById('changePasswordForm');
			const passwordInput = document.getElementById('password');
			const confirmPasswordInput = document.getElementById('password_confirmation');
			const togglePassword = document.getElementById('togglePassword');
			const togglePasswordConfirm = document.getElementById('togglePasswordConfirm');

			// Password strength elements
			const strengthBar = document.getElementById('passwordStrength');
			const strengthText = document.getElementById('passwordStrengthText');

			// Requirement indicators
			const reqLength = document.getElementById('requirement-length');
			const reqNumber = document.getElementById('requirement-number');
			const reqNotPassword = document.getElementById('requirement-not-password');

			// Match text
			const matchText = document.getElementById('passwordMatchText');

			// Toggle password visibility
			togglePassword.addEventListener('click', function() {
				const type = passwordInput.type === 'password' ? 'text' : 'password';
				passwordInput.type = type;
			});

			togglePasswordConfirm.addEventListener('click', function() {
				const type = confirmPasswordInput.type === 'password' ? 'text' : 'password';
				confirmPasswordInput.type = type;
			});

			// Update requirement indicator
			function updateRequirement(element, isValid) {
				if (isValid) {
					element.classList.remove('text-gray-500');
					element.classList.add('text-green-600');
					element.querySelector('svg').innerHTML =
						'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>';
				} else {
					element.classList.remove('text-green-600');
					element.classList.add('text-gray-500');
					element.querySelector('svg').innerHTML =
						'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>';
				}
			}

			// Check password strength
			function checkPasswordStrength(password) {
				let strength = 0;
				const checks = {
					length: password.length >= 8,
					number: /\d/.test(password),
					notPassword: !password.toLowerCase().includes('password')
				};

				// Update requirements
				updateRequirement(reqLength, checks.length);
				updateRequirement(reqNumber, checks.number);
				updateRequirement(reqNotPassword, checks.notPassword);

				// Calculate strength
				if (checks.length) strength += 33;
				if (checks.number) strength += 33;
				if (checks.notPassword) strength += 34;

				// Update strength bar
				strengthBar.style.width = strength + '%';

				if (strength < 50) {
					strengthBar.className = 'h-full rounded-full bg-red-500 transition-all duration-300';
					strengthText.textContent = 'Password lemah';
					strengthText.className = 'mt-1 text-xs text-red-600';
				} else if (strength < 100) {
					strengthBar.className = 'h-full rounded-full bg-yellow-500 transition-all duration-300';
					strengthText.textContent = 'Password sedang';
					strengthText.className = 'mt-1 text-xs text-yellow-600';
				} else {
					strengthBar.className = 'h-full rounded-full bg-green-500 transition-all duration-300';
					strengthText.textContent = 'Password kuat';
					strengthText.className = 'mt-1 text-xs text-green-600';
				}

				return checks;
			}

			// Real-time password validation
			passwordInput.addEventListener('input', function() {
				checkPasswordStrength(this.value);
				checkPasswordMatch();
			});

			// Check password match
			function checkPasswordMatch() {
				const password = passwordInput.value;
				const confirmPassword = confirmPasswordInput.value;

				if (confirmPassword === '') {
					matchText.textContent = '';
					return;
				}

				if (password === confirmPassword) {
					matchText.textContent = '✓ Password cocok';
					matchText.className = 'mt-1 text-xs text-green-600';
				} else {
					matchText.textContent = '✗ Password tidak cocok';
					matchText.className = 'mt-1 text-xs text-red-600';
				}
			}

			confirmPasswordInput.addEventListener('input', checkPasswordMatch);

			// Form validation on submit
			form.addEventListener('submit', function(e) {
				e.preventDefault();

				const password = passwordInput.value;
				const confirmPassword = confirmPasswordInput.value;

				// Validation checks
				if (password.length < 8) {
					alert('❌ Password harus minimal 8 karakter!');
					passwordInput.focus();
					return false;
				}

				if (!/\d/.test(password)) {
					alert('❌ Password harus mengandung minimal 1 angka!');
					passwordInput.focus();
					return false;
				}

				if (password.toLowerCase().includes('password')) {
					alert('❌ Password tidak boleh mengandung kata "password"!');
					passwordInput.focus();
					return false;
				}

				if (password !== confirmPassword) {
					alert('❌ Password dan konfirmasi password tidak cocok!');
					confirmPasswordInput.focus();
					return false;
				}

				// If all validations pass
				this.submit();
			});
		});
	</script>
@endsection
