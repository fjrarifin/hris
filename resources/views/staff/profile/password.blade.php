@extends('layouts.app')

@section('title', 'Ganti Password')
@section('page-title', 'Ganti Password')

@section('content')

	<div class="container-fluid px-0">
		<div class="row justify-content-center">
			<div class="col-lg-6 col-md-8">
				<div class="card card-outline card-primary rounded-3xl shadow-sm">
					<div class="card-header d-flex align-items-center justify-content-between">
						<h3 class="card-title mb-0">
							<i class="fas fa-key text-primary mr-2"></i>
							Ganti Password
						</h3>

						<a href="{{ route('staff.profile.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill ml-auto">
							<i class="fas fa-arrow-left mr-1"></i>
							Kembali
						</a>
					</div>

					<div class="card-body">
						@if ($errors->any())
							<div class="alert alert-danger">
								<ul class="mb-0 pl-3">
									@foreach ($errors->all() as $error)
										<li>{{ $error }}</li>
									@endforeach
								</ul>
							</div>
						@endif

						<form method="POST" action="{{ route('staff.profile.password.update') }}" id="changePasswordForm">
							@csrf
							@method('PUT')

							<div class="form-group">
								<label class="text-muted small" for="current_password">Password Saat Ini</label>
								<div class="input-group">
									<input type="password" name="current_password" id="current_password"
										class="form-control rounded-left-xl @error('current_password') is-invalid @enderror"
										autocomplete="current-password" required>
									<div class="input-group-append">
										<button class="btn btn-outline-secondary toggle-password" type="button"
											data-target="current_password" title="Tampilkan password">
											<i class="fas fa-eye"></i>
										</button>
									</div>
								</div>
							</div>

							<div class="form-group">
								<label class="text-muted small" for="password">Password Baru</label>
								<div class="input-group">
									<input type="password" name="password" id="password"
										class="form-control rounded-left-xl @error('password') is-invalid @enderror"
										autocomplete="new-password" minlength="8" required>
									<div class="input-group-append">
										<button class="btn btn-outline-secondary toggle-password" type="button"
											data-target="password" title="Tampilkan password">
											<i class="fas fa-eye"></i>
										</button>
									</div>
								</div>
								<div class="mt-2">
									<div class="progress" style="height: 6px;">
										<div id="passwordStrength" class="progress-bar" style="width: 0%;"></div>
									</div>
									<div id="passwordStrengthText" class="small text-muted mt-1">Minimal 8 karakter.</div>
								</div>
							</div>

							<div class="form-group">
								<label class="text-muted small" for="password_confirmation">Konfirmasi Password Baru</label>
								<div class="input-group">
									<input type="password" name="password_confirmation" id="password_confirmation"
										class="form-control rounded-left-xl" autocomplete="new-password" minlength="8" required>
									<div class="input-group-append">
										<button class="btn btn-outline-secondary toggle-password" type="button"
											data-target="password_confirmation" title="Tampilkan password">
											<i class="fas fa-eye"></i>
										</button>
									</div>
								</div>
								<div id="passwordMatchText" class="small text-muted mt-1"></div>
							</div>

							<div class="d-flex justify-content-end mt-4">
								<a href="{{ route('staff.profile.index') }}" class="btn btn-outline-secondary rounded-pill mr-2">
									Batal
								</a>
								<button type="submit" class="btn btn-primary rounded-pill px-4">
									<i class="fas fa-save mr-1"></i>
									Simpan Password
								</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>

@endsection

@push('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const password = document.getElementById('password');
			const confirmation = document.getElementById('password_confirmation');
			const strengthBar = document.getElementById('passwordStrength');
			const strengthText = document.getElementById('passwordStrengthText');
			const matchText = document.getElementById('passwordMatchText');

			document.querySelectorAll('.toggle-password').forEach(function (button) {
				button.addEventListener('click', function () {
					const input = document.getElementById(button.dataset.target);
					const icon = button.querySelector('i');

					input.type = input.type === 'password' ? 'text' : 'password';
					icon.classList.toggle('fa-eye');
					icon.classList.toggle('fa-eye-slash');
				});
			});

			function updateStrength() {
				const value = password.value;
				let score = 0;

				if (value.length >= 8) score++;
				if (/\d/.test(value)) score++;
				if (/[A-Z]/.test(value)) score++;
				if (/[^A-Za-z0-9]/.test(value)) score++;

				const percent = score * 25;
				strengthBar.style.width = percent + '%';
				strengthBar.className = 'progress-bar';

				if (!value) {
					strengthBar.style.width = '0%';
					strengthText.textContent = 'Minimal 8 karakter.';
					strengthText.className = 'small text-muted mt-1';
				} else if (score <= 1) {
					strengthBar.classList.add('bg-danger');
					strengthText.textContent = 'Password masih lemah.';
					strengthText.className = 'small text-danger mt-1';
				} else if (score <= 3) {
					strengthBar.classList.add('bg-warning');
					strengthText.textContent = 'Password cukup, lebih baik tambahkan angka/huruf besar/simbol.';
					strengthText.className = 'small text-warning mt-1';
				} else {
					strengthBar.classList.add('bg-success');
					strengthText.textContent = 'Password kuat.';
					strengthText.className = 'small text-success mt-1';
				}
			}

			function updateMatch() {
				if (!confirmation.value) {
					matchText.textContent = '';
					matchText.className = 'small text-muted mt-1';
					return;
				}

				if (password.value === confirmation.value) {
					matchText.textContent = 'Password cocok.';
					matchText.className = 'small text-success mt-1';
				} else {
					matchText.textContent = 'Password belum cocok.';
					matchText.className = 'small text-danger mt-1';
				}
			}

			password.addEventListener('input', function () {
				updateStrength();
				updateMatch();
			});

			confirmation.addEventListener('input', updateMatch);
		});
	</script>
@endpush
