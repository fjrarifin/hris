@extends('layouts.app')

@section('title', 'Profile')

@section('content')

	<div class="container-fluid px-0">

		{{-- PROFILE HEADER --}}
		<div class="card card-outline card-primary mb-4 rounded-3xl shadow-sm">
			<div class="card-body">

				{{-- ================= MOBILE ================= --}}
				<div class="d-md-none text-center">

					<div class="d-flex justify-content-center mb-3">
						@if ($user->photo)
							<img src="{{ asset('storage/' . $user->photo) }}" class="rounded-circle shadow" width="110" height="110"
								style="object-fit:cover;">
						@else
							<div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white shadow"
								style="width:110px;height:110px;font-size:36px;">
								{{ strtoupper(substr($user->karyawan->nama_karyawan, 0, 1)) }}
							</div>
						@endif
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

					<a href="#" class="btn btn-outline-secondary btn-block rounded-pill">
						<i class="fas fa-key mr-1"></i>
						Ganti Password
					</a>

				</div>



				{{-- ================= DESKTOP ================= --}}
				<div class="d-none d-md-flex align-items-center justify-content-between">

					<div class="d-flex align-items-center">

						{{-- FOTO --}}
						@if ($user->photo)
							<img src="{{ asset('storage/' . $user->photo) }}" class="rounded-circle mr-4 shadow" width="120" height="120"
								style="object-fit:cover;">
						@else
							<div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mr-4 text-white shadow"
								style="width:120px;height:120px;font-size:40px;">
								{{ strtoupper(substr($user->karyawan->nama_karyawan, 0, 1)) }}
							</div>
						@endif

						{{-- INFO --}}
						<div>
							<h4 class="font-weight-bold mb-1">
								{{ $user->karyawan->nama_karyawan }}
							</h4>

							<div class="text-muted mb-2">
								{{ $user->karyawan->jabatan }} • {{ $user->karyawan->department }}
							</div>

							<span class="badge badge-success px-3 py-1">
								{{ $user->karyawan->status_karyawan }}
							</span>

							<div class="small text-muted mt-3">
								Bergabung sejak
								<strong>{{ \Carbon\Carbon::parse($user->karyawan->join_date)->format('d M Y') }}</strong>
								• {{ \Carbon\Carbon::parse($user->karyawan->join_date)->diffForHumans(null, true) }}
							</div>
						</div>

					</div>

					{{-- BUTTON --}}
					<div>
						<a href="#" class="btn btn-outline-secondary rounded-pill px-4">
							<i class="fas fa-key mr-1"></i>
							Ganti Password
						</a>
					</div>

				</div>

			</div>
		</div>

		{{-- DETAIL & CONTACT --}}
		<div class="row">

			{{-- DETAIL --}}
			<div class="col-md-6">
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
								<div class="text-muted">Divisi</div>
								<div class="font-weight-semibold">{{ $user->karyawan->divisi }}</div>
							</div>

							<div class="col-6 mb-3">
								<div class="text-muted">Atasan</div>
								<div class="font-weight-semibold">{{ $user->karyawan->nama_atasan_langsung }}</div>
							</div>

							<div class="col-6 mb-3">
								<div class="text-muted">Unit</div>
								<div class="font-weight-semibold">{{ $user->karyawan->unit }}</div>
							</div>

						</div>

					</div>
				</div>
			</div>

			{{-- UPDATE CONTACT --}}
			<div class="col-md-6">
				<div class="card card-outline card-primary rounded-3xl shadow-sm">
					<div class="card-body">

						<h6 class="font-weight-bold mb-3">
							<i class="fas fa-address-book text-primary mr-2"></i>
							Data Kontak
						</h6>

						<form method="POST" action="{{ route('staff.profile.update') }}" enctype="multipart/form-data">
							@csrf
							@method('PUT')

							<div class="form-group">
								<label class="text-muted small">No HP</label>
								<input type="text" name="no_hp" class="form-control rounded-xl" value="{{ $user->karyawan->no_hp }}">
							</div>

							<div class="form-group">
								<label class="text-muted small">Email</label>
								<input type="email" name="email" class="form-control rounded-xl" value="{{ $user->email }}">
							</div>

							<div class="form-group">
								<label class="text-muted small">Foto Profil</label>
								<input type="file" name="photo" class="form-control-file">
							</div>

							<button class="btn btn-primary rounded-pill px-4">
								<i class="fas fa-save mr-1"></i>
								Update Profil
							</button>

						</form>

					</div>
				</div>
			</div>

		</div>

	</div>

@endsection
