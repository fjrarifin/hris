@extends('layouts.app')

@section('title', 'Profil')

@section('content')
	@php
		$canUpdatePhone = !$user->karyawan->phone_updated_at;
		$canUpdateEmail = !$user->email_updated_at;
	@endphp

	<style>
		.profile-avatar {
			aspect-ratio: 1 / 1;
			border-radius: 50%;
			display: block;
			object-fit: cover;
		}

		.profile-avatar-button {
			background: transparent;
			border: 0;
			cursor: pointer;
			padding: 0;
		}

		.profile-edit-badge {
			display: inline-flex;
			align-items: center;
			border-radius: 999px;
			font-size: 10px;
			font-weight: 800;
			padding: 3px 8px;
		}
	</style>

	<div class="container-fluid px-0">

		{{-- PROFILE HEADER --}}
		<div class="card card-outline card-primary mb-4 rounded-3xl shadow-sm d-md-none">
			<div class="card-body">

				{{-- ================= MOBILE ================= --}}
				<div class="d-md-none text-center">

					<div class="d-flex justify-content-center mb-3">
						<button type="button" class="profile-avatar-button" data-toggle="modal" data-target="#photoActionModal">
							@if ($user->photo)
								<img src="{{ asset('storage/' . $user->photo) }}" class="profile-avatar shadow" width="110" height="110">
							@else
								<div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white shadow"
									style="width:110px;height:110px;font-size:36px;">
									{{ strtoupper(substr($user->karyawan->nama_karyawan, 0, 1)) }}
								</div>
							@endif
						</button>
					</div>

					<h5 class="font-weight-bold mb-1">
						{{ $user->karyawan->nama_karyawan }}
					</h5>

					<div class="text-muted small mb-2">
						{{ $user->karyawan->jabatan }} • {{ $user->karyawan->department }}
					</div>

					<span class="badge badge-success mb-3 px-3 py-1">
						{{ $user->karyawan->status_karyawan }}
					</span>

					<div class="small text-muted mb-3">
						Bergabung sejak
						<strong>{{ \Carbon\Carbon::parse($user->karyawan->join_date)->format('d M Y') }}</strong>
						• {{ \Carbon\Carbon::parse($user->karyawan->join_date)->diffForHumans(null, true) }}
					</div>

					<a href="{{ route('staff.profile.password') }}" class="btn btn-outline-secondary btn-block rounded-pill">
						<i class="fas fa-key mr-1"></i>
						Ganti Password
					</a>

				</div>
			</div>
		</div>

		{{-- DETAIL & CONTACT --}}
		<div class="row align-items-start">

			{{-- DESKTOP PROFILE --}}
			<div class="col-md-4 d-none d-md-block">
				<div class="card card-outline card-primary mb-4 rounded-3xl shadow-sm">
					<div class="card-body text-center py-4">
						<div class="d-flex justify-content-center mb-3">
							<button type="button" class="profile-avatar-button" data-toggle="modal" data-target="#photoActionModal">
								@if ($user->photo)
									<img src="{{ asset('storage/' . $user->photo) }}" class="profile-avatar shadow" width="132" height="132">
								@else
									<div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white shadow"
										style="width:132px;height:132px;font-size:44px;">
										{{ strtoupper(substr($user->karyawan->nama_karyawan, 0, 1)) }}
									</div>
								@endif
							</button>
						</div>

						<h4 class="font-weight-bold mb-1">
							{{ $user->karyawan->nama_karyawan }}
						</h4>

						<div class="text-muted mb-2">
							{{ $user->karyawan->jabatan }} &bull; {{ $user->karyawan->departement }}
						</div>

						<span class="badge badge-success px-3 py-1">
							{{ $user->karyawan->status_karyawan }}
						</span>

						<div class="small text-muted mt-3">
							Bergabung sejak<br>
							<strong>{{ \Carbon\Carbon::parse($user->karyawan->join_date)->format('d M Y') }}</strong>
							<div>{{ \Carbon\Carbon::parse($user->karyawan->join_date)->diffForHumans(null, true) }}</div>
						</div>

						<a href="{{ route('staff.profile.password') }}" class="btn btn-outline-secondary btn-block rounded-pill mt-4">
							<i class="fas fa-key mr-1"></i>
							Ganti Password
						</a>
					</div>
				</div>
			</div>

			<div class="col-md-8">

			{{-- DETAIL --}}
			<div>
				<div class="card card-outline card-primary mb-4 rounded-3xl shadow-sm">
					<div class="card-body">

						<h6 class="font-weight-bold mb-3">
							<i class="fas fa-id-badge text-primary mr-2"></i>
							Informasi Karyawan
						</h6>

						<div class="row text-sm">

							<div class="col-6 mb-3">
								<div class="text-muted">NIK</div>
								<div class="font-weight-semibold">{{ $user->karyawan->nik }}</div>
							</div>

							<div class="col-6 mb-3">
								<div class="text-muted">Departemen</div>
								<div class="font-weight-semibold">{{ $user->karyawan->departement }}</div>
							</div>

							<div class="col-6 mb-3">
								<div class="text-muted">Atasan</div>
								<div class="font-weight-semibold">{{ $user->karyawan->nama_atasan_langsung }}</div>
							</div>

							<div class="col-6 mb-3">
								<div class="text-muted">Atasan Tidak Langsung</div>
								<div class="font-weight-semibold">{{ $user->karyawan->atasan_tidak_langsung ?: '-' }}</div>
							</div>

							<div class="col-6 mb-3">
								<div class="text-muted">Unit</div>
								<div class="font-weight-semibold">{{ $user->karyawan->unit }}</div>
							</div>

							<div class="col-6 mb-3">
								<div class="text-muted">Tanggal Lahir</div>
								<div class="font-weight-semibold">
									{{ $user->karyawan->tanggal_lahir ? \Carbon\Carbon::parse($user->karyawan->tanggal_lahir)->format('d M Y') : '-' }}
								</div>
							</div>

							<div class="col-6 mb-3">
								<div class="text-muted">No. Rekening</div>
								<div class="font-weight-semibold">{{ $user->karyawan->no_rekening ?: '-' }}</div>
							</div>

							<div class="col-6 mb-3">
								<div class="text-muted">Bank</div>
								<div class="font-weight-semibold">{{ $user->karyawan->bank ?: '-' }}</div>
							</div>

							<div class="col-6 mb-3">
								<div class="text-muted">No. HP</div>
								<div class="font-weight-semibold">{{ $user->karyawan->no_hp }}</div>
								{{-- @if ($canUpdatePhone)
									<span class="profile-edit-badge bg-warning text-dark">Bisa diganti 1x</span>
								@else
									<span class="profile-edit-badge bg-light text-muted">Sudah pernah diganti</span>
								@endif --}}
							</div>

							<div class="col-6 mb-3">
								<div class="text-muted">Email</div>
								<div class="font-weight-semibold">{{ $user->email }}</div>
								{{-- @if ($canUpdateEmail)
									<span class="profile-edit-badge bg-warning text-dark">Bisa diganti 1x</span>
								@else
									<span class="profile-edit-badge bg-light text-muted">Sudah pernah diganti</span>
								@endif --}}
							</div>

						</div>

						<div class="rounded-xl border bg-light p-3">
							<div class="d-flex flex-column flex-md-row align-items-md-center">
								<div class="text-sm text-muted">
									<i class="fas fa-info-circle text-warning mr-1"></i>
									Nomor telepon dan email hanya bisa diganti satu kali. Pastikan data baru sudah benar sebelum disimpan.
								</div>
								<button type="button" class="btn btn-primary btn-sm rounded-pill ml-md-auto mt-3 mt-md-0"
									data-toggle="modal" data-target="#updateContactModal"
									{{ !$canUpdatePhone && !$canUpdateEmail ? 'disabled' : '' }}>
									<i class="fas fa-edit mr-1"></i>
									Edit Kontak
								</button>
							</div>
						</div>

					</div>
				</div>
			</div>

			</div>

		</div>

	</div>

	{{-- UPDATE CONTACT MODAL --}}
	<div class="modal fade" id="updateContactModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content rounded-3xl">
				<form method="POST" action="{{ route('staff.profile.update') }}" id="contactUpdateForm">
					@csrf
					@method('PUT')

					<div class="modal-header">
						<h5 class="modal-title">Edit Kontak</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>

					<div class="modal-body">
						<div class="alert alert-warning rounded-xl text-sm">
							<i class="fas fa-exclamation-triangle mr-1"></i>
							Nomor telepon dan email hanya bisa diganti satu kali. Setelah disimpan, field yang berubah tidak bisa diedit lagi dari profil.
						</div>

						<div class="form-group">
							<label class="font-weight-semibold">No. HP</label>
							<input type="text" name="no_hp" class="form-control rounded-xl"
								value="{{ old('no_hp', $user->karyawan->no_hp) }}"
								data-original="{{ $user->karyawan->no_hp }}"
								{{ $canUpdatePhone ? '' : 'readonly' }}>
							@if (!$canUpdatePhone)
								<small class="text-muted">Nomor telepon sudah pernah diganti pada
									{{ $user->karyawan->phone_updated_at?->format('d M Y H:i') }}.</small>
							@else
								<small class="text-warning font-weight-bold">Pastikan nomor baru aktif sebelum disimpan.</small>
							@endif
						</div>

						<div class="form-group mb-0">
							<label class="font-weight-semibold">Email</label>
							<input type="email" name="email" class="form-control rounded-xl"
								value="{{ old('email', $user->email) }}"
								data-original="{{ $user->email }}"
								{{ $canUpdateEmail ? '' : 'readonly' }}>
							@if (!$canUpdateEmail)
								<small class="text-muted">Email sudah pernah diganti pada
									{{ $user->email_updated_at?->format('d M Y H:i') }}.</small>
							@else
								<small class="text-warning font-weight-bold">Pastikan email baru valid sebelum disimpan.</small>
							@endif
						</div>
					</div>

					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary rounded-pill" data-dismiss="modal">
							Batal
						</button>
						<button type="submit" class="btn btn-primary rounded-pill px-4">
							<i class="fas fa-save mr-1"></i>
							Simpan Kontak
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	{{-- PHOTO ACTION MODAL --}}
	<div class="modal fade" id="photoActionModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content rounded-3xl">
				<div class="modal-header">
					<h5 class="modal-title">Foto Profil</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>

				<div class="modal-body">
					<div class="d-flex justify-content-center mb-4">
						@if ($user->photo)
							<img src="{{ asset('storage/' . $user->photo) }}" class="profile-avatar shadow" width="132" height="132">
						@else
							<div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white shadow"
								style="width:132px;height:132px;font-size:44px;">
								{{ strtoupper(substr($user->karyawan->nama_karyawan, 0, 1)) }}
							</div>
						@endif
					</div>

					<div class="d-flex flex-column">
						@if ($user->photo)
							<a href="{{ asset('storage/' . $user->photo) }}" target="_blank" rel="noopener"
								class="btn btn-outline-primary rounded-pill mb-2">
								<i class="fas fa-external-link-alt mr-1"></i>
								Lihat Foto
							</a>
						@else
							<button type="button" class="btn btn-outline-secondary rounded-pill mb-2" disabled>
								<i class="fas fa-external-link-alt mr-1"></i>
								Lihat Foto
							</button>
						@endif

						<button type="button" class="btn btn-primary rounded-pill" data-dismiss="modal" data-toggle="modal"
							data-target="#updatePhotoModal">
							<i class="fas fa-camera mr-1"></i>
							Perbarui Foto Profil
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	{{-- UPDATE PHOTO MODAL --}}
	<div class="modal fade" id="updatePhotoModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content rounded-3xl">
				<form method="POST" action="{{ route('staff.profile.update') }}" enctype="multipart/form-data">
					@csrf
					@method('PUT')
					<input type="hidden" name="no_hp" value="{{ $user->karyawan->no_hp }}">
					<input type="hidden" name="email" value="{{ $user->email }}">

					<div class="modal-header">
						<h5 class="modal-title">Perbarui Foto Profil</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>

					<div class="modal-body">
						<div class="form-group mb-0">
							<label class="text-muted small">Foto Profil</label>

							<div id="photoDropzone"
								class="border rounded-xl p-4 text-center bg-light"
								style="cursor:pointer; border-style:dashed !important;">

								<i class="fas fa-cloud-upload-alt fa-2x text-primary mb-2"></i>
								<div class="font-weight-bold">Drag & drop foto di sini</div>
								<div class="text-muted small">atau klik untuk pilih file</div>

								<div id="photoFileName" class="small text-muted mt-2"></div>

								<input type="file"
									id="photoInput"
									name="photo"
									class="d-none"
									accept="image/*">
							</div>

							<button type="button" id="previewPhotoBtn" class="btn btn-outline-primary btn-sm rounded-pill mt-3 d-none">
								<i class="fas fa-eye mr-1"></i>
								Pratinjau Foto
							</button>

							<div id="photoPreviewWrapper" class="mt-3 d-none text-center">
								<img id="photoPreview"
									src=""
									class="profile-avatar mx-auto shadow"
									width="120"
									height="120">

								<div class="small text-muted mt-2">
									Pratinjau foto baru sebelum disimpan
								</div>
							</div>
						</div>
					</div>

					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary rounded-pill" data-dismiss="modal">
							Batal
						</button>
						<button class="btn btn-primary rounded-pill px-4">
							<i class="fas fa-save mr-1"></i>
							Simpan Foto
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>

@push('scripts')
<script>
	document.addEventListener('DOMContentLoaded', function () {
		const contactForm = document.getElementById('contactUpdateForm');

		if (contactForm) {
			contactForm.addEventListener('submit', function(e) {
				e.preventDefault();

				const phoneInput = contactForm.querySelector('[name="no_hp"]');
				const emailInput = contactForm.querySelector('[name="email"]');
				const phoneChanged = phoneInput.value.trim() !== (phoneInput.dataset.original || '').trim();
				const emailChanged = emailInput.value.trim().toLowerCase() !== (emailInput.dataset.original || '').trim().toLowerCase();
				const changedFields = [];

				if (phoneChanged) changedFields.push('nomor telepon');
				if (emailChanged) changedFields.push('email');

				if (!phoneChanged && !emailChanged) {
					Swal.fire({
						icon: 'info',
						title: 'Tidak Ada Perubahan',
						text: 'Nomor telepon dan email tidak berubah.'
					});
					return;
				}

				Swal.fire({
					title: 'Simpan Perubahan Kontak?',
					html: 'Anda akan menggunakan kesempatan edit 1x untuk <b>' + changedFields.join(' dan ') + '</b>. Pastikan data sudah benar.',
					icon: 'warning',
					showCancelButton: true,
					confirmButtonText: 'Ya, Simpan',
					cancelButtonText: 'Batal',
					confirmButtonColor: '#007bff'
				}).then((result) => {
					if (!result.isConfirmed) return;

					const submitBtn = contactForm.querySelector('button[type="submit"]');
					submitBtn.disabled = true;
					submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menyimpan...';
					contactForm.submit();
				});
			});
		}

		const dropzone = document.getElementById('photoDropzone');
		const input = document.getElementById('photoInput');
		const fileName = document.getElementById('photoFileName');
		const previewBtn = document.getElementById('previewPhotoBtn');
		const previewWrapper = document.getElementById('photoPreviewWrapper');
		const preview = document.getElementById('photoPreview');

		let selectedFile = null;

		dropzone.addEventListener('click', function () {
			input.click();
		});

		dropzone.addEventListener('dragover', function (e) {
			e.preventDefault();
			dropzone.classList.add('border-primary');
		});

		dropzone.addEventListener('dragleave', function () {
			dropzone.classList.remove('border-primary');
		});

		dropzone.addEventListener('drop', function (e) {
			e.preventDefault();
			dropzone.classList.remove('border-primary');

			if (e.dataTransfer.files.length > 0) {
				handleSelectedFile(e.dataTransfer.files[0]);
			}
		});

		input.addEventListener('change', function () {
			if (input.files.length > 0) {
				handleSelectedFile(input.files[0]);
			}
		});

		previewBtn.addEventListener('click', function () {
			if (!selectedFile) return;

			const reader = new FileReader();

			reader.onload = function (e) {
				preview.src = e.target.result;
				previewWrapper.classList.remove('d-none');
			};

			reader.readAsDataURL(selectedFile);
		});

		async function handleSelectedFile(file) {
			if (!file.type.startsWith('image/')) {
				alert('File harus berupa gambar.');
				input.value = '';
				selectedFile = null;
				fileName.textContent = '';
				previewBtn.classList.add('d-none');
				previewWrapper.classList.add('d-none');
				return;
			}

			try {
				selectedFile = await makeSquareImage(file);
			} catch (error) {
				alert('Foto tidak bisa diproses. Silakan pilih file gambar lain.');
				input.value = '';
				selectedFile = null;
				fileName.textContent = '';
				previewBtn.classList.add('d-none');
				previewWrapper.classList.add('d-none');
				return;
			}

			const transfer = new DataTransfer();
			transfer.items.add(selectedFile);
			input.files = transfer.files;

			fileName.textContent = 'File dipilih: ' + file.name;
			previewBtn.classList.remove('d-none');
			previewWrapper.classList.add('d-none');
			preview.src = '';
		}

		function makeSquareImage(file) {
			return new Promise(function (resolve, reject) {
				const image = new Image();
				const url = URL.createObjectURL(file);

				image.onload = function () {
					const side = Math.min(image.naturalWidth, image.naturalHeight);
					const sourceX = (image.naturalWidth - side) / 2;
					const sourceY = (image.naturalHeight - side) / 2;
					const canvasSize = 512;
					const canvas = document.createElement('canvas');
					const context = canvas.getContext('2d');

					canvas.width = canvasSize;
					canvas.height = canvasSize;
					context.drawImage(image, sourceX, sourceY, side, side, 0, 0, canvasSize, canvasSize);

					canvas.toBlob(function (blob) {
						URL.revokeObjectURL(url);

						if (!blob) {
							reject(new Error('Canvas conversion failed'));
							return;
						}

						const extension = file.type === 'image/png' ? 'png' : 'jpg';
						const fileNameWithoutExtension = file.name.replace(/\.[^.]+$/, '') || 'profile-photo';

						resolve(new File(
							[blob],
							fileNameWithoutExtension + '-square.' + extension,
							{ type: blob.type, lastModified: Date.now() }
						));
					}, file.type === 'image/png' ? 'image/png' : 'image/jpeg', 0.9);
				};

				image.onerror = function () {
					URL.revokeObjectURL(url);
					reject(new Error('Image load failed'));
				};

				image.src = url;
			});
		}
	});
</script>
@endpush

@endsection
