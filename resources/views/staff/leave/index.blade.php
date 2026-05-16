@extends('layouts.app')

@section('title', 'Pengajuan Cuti')
@section('page-title', 'Pengajuan Cuti')

@section('content')
	<style>
		@media (max-width: 768px) {
			.desktop-table {
				display: none;
			}
		}

		@media (min-width: 769px) {
			.mobile-card {
				display: none;
			}
		}

		.mobile-card .card {
			margin-bottom: 15px;
		}

		/* Modal Center Fix */
		.modal.show {
			display: flex !important;
			align-items: center;
			justify-content: center;
		}

		.modal-dialog-centered {
			margin: auto;
		}
	</style>



	<div class="row">
		{{-- Riwayat Pengajuan Cuti --}}
		<div class="col-md-6">
			<div class="card card-primary card-outline rounded-xl text-xs">
				{{-- HEADER --}}
				<div class="card-header d-flex align-items-center">
					<h3 class="card-title mb-0">Riwayat Pengajuan Cuti</h3>

					<button class="btn btn-primary btn-sm ml-auto rounded-xl" data-toggle="modal" data-target="#modalPengajuanCuti">
						<i class="fas fa-plus"></i> Ajukan Cuti
					</button>
				</div>


				{{-- BODY --}}
				<div class="card-body">

					<div class="desktop-table">
						<div class="row text-center">

							<div class="col-md-4 mb-2">
								<div class="rounded-lg border py-2">
									<div class="text-muted small">Total Accrued</div>
									<h5 class="font-weight-bold mb-0">
										{{ $total }} <small class="text-muted">hari</small>
									</h5>
								</div>
							</div>

							<div class="col-md-4 mb-2">
								<div class="rounded-lg border py-2">
									<div class="text-muted small">Digunakan</div>
									<h5 class="font-weight-bold text-danger mb-0">
										{{ $used }} <small class="text-muted">hari</small>
									</h5>
								</div>
							</div>

							<div class="col-md-4 mb-2">
								<div class="bg-light rounded-lg border py-2">
									<div class="text-muted small">Tersedia</div>
									<h4 class="font-weight-bold text-success mb-0">
										{{ $available }} <small class="text-muted">hari</small>
									</h4>
								</div>
							</div>

						</div>
						<table class="table-bordered table-hover table">
							<thead>
								<tr>
									<th>Tanggal</th>
									<th>Durasi</th>
									<th>Status</th>
									<th>Reason</th>
									<th width="80">Aksi</th>
								</tr>
							</thead>

							<tbody>

								@forelse($requests as $r)
									@php
										if ($r->status === 'rejected') {
										    $label = 'Ditolak';
										    $class = 'danger';
										} elseif ($r->status === 'cancelled') {
										    $label = 'Dibatalkan';
										    $class = 'secondary';
										} elseif ($r->hr_approved_at) {
										    $label = 'Disetujui HR';
										    $class = 'success';
										} elseif ($r->manager_approved_at) {
										    $label = 'Disetujui Atasan';
										    $class = 'info';
										} else {
										    $label = 'Menunggu';
										    $class = 'warning';
										}
									@endphp
									<tr>
										<td>{{ $r->start_date }} s/d {{ $r->end_date }}</td>
										<td>{{ \Carbon\Carbon::parse($r->start_date)->diffInDays($r->end_date) + 1 }} hari</td>
										<td>
											<span class="badge badge-{{ $class }}">
												{{ $label }}
											</span>
										</td>
										<td>{{ $r->reject_reason }}
										<td class="text-center">
											@if ($r->status === 'pending')
												<form method="POST" action="{{ route('staff.leave.destroy', $r->id) }}" class="d-inline form-delete">
													@csrf
													@method('DELETE')
													<button class="btn btn-danger btn-xs" title="Hapus">
														<i class="fas fa-trash"></i>
													</button>
												</form>
											@else
												<span class="text-muted">-</span>
											@endif
										</td>
									</tr>
								@empty
									<tr>
										<td colspan="5" class="text-muted text-center">
											Belum ada pengajuan cuti
										</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>

					<div class="mobile-card">
						<div class="row text-center">

						<div class="col-4 mb-2">
							<div class="rounded-lg border py-2">
								<div class="text-muted small">Total Accrued</div>
								<h5 class="font-weight-bold mb-0">
									{{ $total }} <small class="text-muted">hari</small>
								</h5>
							</div>
						</div>

						<div class="col-4 mb-2">
							<div class="rounded-lg border py-2">
								<div class="text-muted small">Digunakan</div>
								<h5 class="font-weight-bold text-danger mb-0">
									{{ $used }} <small class="text-muted">hari</small>
								</h5>
							</div>
						</div>

						<div class="col-4 mb-2">
							<div class="bg-light rounded-lg border py-2">
								<div class="text-muted small">Tersedia</div>
								<h4 class="font-weight-bold text-success mb-0">
									{{ $available }} <small class="text-muted">hari</small>
								</h4>
							</div>
						</div>

					</div>
						@forelse($requests as $r)
							@php
								if ($r->status === 'rejected') {
								    $label = 'Ditolak';
								    $class = 'danger';
								} elseif ($r->status === 'cancelled') {
								    $label = 'Dibatalkan';
								    $class = 'secondary';
								} elseif ($r->hr_approved_at) {
								    $label = 'Disetujui HR';
								    $class = 'success';
								} elseif ($r->manager_approved_at) {
								    $label = 'Disetujui Atasan';
								    $class = 'info';
								} else {
								    $label = 'Menunggu';
								    $class = 'warning';
								}
							@endphp

							<div class="card shadow-sm">
								<div class="card-body">

									<div class="d-flex justify-content-between align-items-center">
										<strong>
											{{ \Carbon\Carbon::parse($r->start_date)->format('d M Y') }}
											-
											{{ \Carbon\Carbon::parse($r->end_date)->format('d M Y') }}
										</strong>

										<span class="badge badge-{{ $class }}">
											{{ $label }}
										</span>
									</div>

									<div class="mt-2 text-sm">
										<p class="mb-1">
											<strong>Durasi:</strong>
											{{ \Carbon\Carbon::parse($r->start_date)->diffInDays($r->end_date) + 1 }} hari
										</p>

										<p class="mb-1">
											<strong>Alasan:</strong>
											{{ $r->reason ?? '-' }}
										</p>

										@if ($r->reject_reason)
											<p class="text-danger mb-1">
												<strong>Ditolak karena:</strong>
												{{ $r->reject_reason }}
											</p>
										@endif
									</div>

									@if ($r->status === 'pending')
										<div class="mt-3">
											<form method="POST" action="{{ route('staff.leave.destroy', $r->id) }}" class="form-delete">
												@csrf
												@method('DELETE')
												<button class="btn btn-danger btn-block btn-sm">
													🗑 Hapus Pengajuan
												</button>
											</form>
										</div>
									@endif

								</div>
							</div>

						@empty
							<div class="text-muted text-center">
								Belum ada pengajuan cuti
							</div>
						@endforelse

					</div>

				</div>

			</div>
		</div>

		{{-- Sisa Cuti Tahunan --}}
		<div class="col-md-6">
			<div class="card card-primary card-outline rounded-xl text-xs">
				<div class="card-header">
					<h3 class="card-title mb-0">Sisa Cuti Tahunan</h3>
				</div>

				<div class="card-body">
					<div class="desktop-table">
						<table class="table-bordered table-sm table">
							<thead>
								<tr>
									<th>Bulan</th>
									<th>Tanggal Dapat</th>
									<th>Expired</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
								@forelse($accruals as $a)
									@php
										if ($a->is_used) {
										    $badge = 'danger';
										    $label = 'Used';
										} elseif ($a->expired_at < now()) {
										    $badge = 'secondary';
										    $label = 'Expired';
										} else {
										    $badge = 'success';
										    $label = 'Active';
										}
									@endphp

									<tr>
										<td>{{ \Carbon\Carbon::create($a->year, $a->month)->format('F Y') }}</td>
										<td>{{ \Carbon\Carbon::parse($a->accrued_at)->format('d M Y') }}</td>
										<td>{{ \Carbon\Carbon::parse($a->expired_at)->format('d M Y') }}</td>
										<td>
											<span class="badge badge-{{ $badge }}">
												{{ $label }}
											</span>
										</td>
									</tr>
								@empty
									<tr>
										<td colspan="4" class="text-muted text-center">
											Belum ada accrual cuti
										</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>

					<div class="mobile-card">
						@forelse($accruals as $a)
							@php
								if ($a->is_used) {
								    $badge = 'danger';
								    $label = 'Used';
								} elseif ($a->expired_at < now()) {
								    $badge = 'secondary';
								    $label = 'Expired';
								} else {
								    $badge = 'success';
								    $label = 'Active';
								}
							@endphp

							<div class="card shadow-sm">
								<div class="card-body">

									<div class="d-flex justify-content-between">
										<strong>
											{{ \Carbon\Carbon::create($a->year, $a->month)->format('F Y') }}
										</strong>
										<span class="badge badge-{{ $badge }}">
											{{ $label }}
										</span>
									</div>

									<div class="mt-2 text-sm">
										<p class="mb-1">
											<strong>Tanggal Dapat:</strong>
											{{ \Carbon\Carbon::parse($a->accrued_at)->format('d M Y') }}
										</p>

										<p class="mb-1">
											<strong>Expired:</strong>
											{{ \Carbon\Carbon::parse($a->expired_at)->format('d M Y') }}
										</p>
									</div>

								</div>
							</div>
						@empty
							<div class="text-muted text-center">
								Belum ada accrual cuti
							</div>
						@endforelse
					</div>

				</div>
			</div>
		</div>
	</div>

	{{-- MODAL PENGAJUAN CUTI --}}
	<div class="modal fade" id="modalPengajuanCuti" tabindex="-1">
		<div class="modal-dialog modal-lg modal-dialog-centered">
			<form method="POST" action="{{ route('staff.leave.store') }}" novalidate>
				@csrf

				<div class="modal-content rounded-3xl border-0 shadow-lg">

					<div class="modal-header border-0 pb-0">
						<h5 class="modal-title font-weight-bold">
							<i class="fas fa-calendar-plus text-primary mr-2"></i>
							Pengajuan Cuti
						</h5>
						<button type="button" class="close" data-dismiss="modal">
							<span>&times;</span>
						</button>
					</div>

					<div class="modal-body pt-3">

						{{-- TANGGAL --}}
						<div class="form-row">
							<div class="form-group col-md-6">
								<label class="font-weight-semibold">Tanggal Mulai</label>
								<input type="date" name="start_date" class="form-control rounded-xl" required>
							</div>

							<div class="form-group col-md-6">
								<label class="font-weight-semibold">Tanggal Selesai</label>
								<input type="date" name="end_date" class="form-control rounded-xl" required>
							</div>
						</div>

						{{-- JENIS CUTI DROPDOWN --}}
						<div class="form-group">
							<label class="font-weight-semibold">
								Jenis Cuti <span class="text-danger">*</span>
							</label>

							<select name="leave_type" class="form-control rounded-xl" required>
								<option value="">-- Pilih Jenis Cuti --</option>
								@foreach (\App\Models\LeaveRequest::LEAVE_TYPES as $key => $label)
									<option value="{{ $key }}" {{ old('leave_type') === $key ? 'selected' : '' }}>
										{{ $label }}
									</option>
								@endforeach
							</select>

							@error('leave_type')
								<small class="text-danger">{{ $message }}</small>
							@enderror
						</div>

						{{-- ALASAN --}}
						<div class="form-group">
							<label class="font-weight-semibold">Alasan <span class="text-danger">*</span></label>
							<textarea name="reason" required class="form-control rounded-xl" rows="3"
							 placeholder="Tuliskan alasan pengajuan cuti..."></textarea>
						</div>

					</div>

					<div class="modal-footer border-0 pt-0">
						<button type="button" class="btn btn-light rounded-pill px-4" data-dismiss="modal">
							Batal
						</button>

						<button type="submit" class="btn btn-primary rounded-pill px-4">
							<i class="fas fa-paper-plane mr-1"></i> Kirim
						</button>

					</div>

				</div>
			</form>
		</div>
	</div>

	<script>
		document.addEventListener('DOMContentLoaded', function() {

			const form = document.querySelector('#modalPengajuanCuti form');
			const startInput = form.querySelector('input[name="start_date"]');
			const endInput = form.querySelector('input[name="end_date"]');

			const today = new Date().toISOString().split('T')[0];

			startInput.setAttribute('min', today);
			endInput.setAttribute('min', today);

			if (!form) return;

			form.addEventListener('submit', function(e) {

				e.preventDefault();

				const startDate = form.start_date.value;
				const endDate = form.end_date.value;
				const leaveType = form.leave_type.value;
				const reason = form.reason.value;

				const today = new Date().toISOString().split('T')[0];

				// ❌ Required validation
				if (!startDate || !endDate || !leaveType || !reason) {
					return toastError('Semua field wajib diisi.');
				}

				// ❌ Start < today
				if (startDate < today) {
					return toastError('Tanggal mulai tidak boleh sebelum hari ini.');
				}

				// ❌ End < start
				if (endDate < startDate) {
					return toastError('Tanggal selesai tidak boleh sebelum tanggal mulai.');
				}

				// ❌ Maksimal 5 hari
				const diffDays = (new Date(endDate) - new Date(startDate)) / (1000 * 60 * 60 * 24) + 1;

				if (diffDays > 5) {
					return toastError('Maksimal pengajuan cuti adalah 5 hari.');
				}

				// ✅ Konfirmasi
				Swal.fire({
					title: 'Ajukan Cuti?',
					text: 'Pastikan data sudah benar.',
					icon: 'question',
					showCancelButton: true,
					confirmButtonText: 'Ya, Ajukan',
					cancelButtonText: 'Batal',
					confirmButtonColor: '#007bff'
				}).then((result) => {

					if (result.isConfirmed) {

						const submitBtn = form.querySelector('button[type="submit"]');
						submitBtn.disabled = true;
						submitBtn.innerHTML =
							'<span class="spinner-border spinner-border-sm"></span> Mengirim...';

						form.submit();
					}

				});

			});

		});


		// DELETE CUTI
		document.querySelectorAll('.form-delete').forEach(form => {
			form.addEventListener('submit', function(e) {
				e.preventDefault();

				Swal.fire({
					title: 'Hapus Pengajuan?',
					text: 'Pengajuan cuti ini akan dihapus dan tidak bisa dikembalikan.',
					icon: 'warning',
					showCancelButton: true,
					confirmButtonText: 'Ya, Hapus',
					cancelButtonText: 'Batal',
					confirmButtonColor: '#dc3545',
					cancelButtonColor: '#6c757d'
				}).then((result) => {
					if (result.isConfirmed) {
						form.submit();
					}
				});
			});
		});

		function toastError(message) {
			Swal.fire({
				toast: true,
				position: 'top-end',
				icon: 'error',
				title: message,
				showConfirmButton: false,
				timer: 3500
			});
		}
	</script>

@endsection
