@extends('layouts.app')

@section('title', 'Pengajuan Public Holiday')
@section('page-title', 'Pengajuan Public Holiday')

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
		<div class="col-12">
			<div class="card card-primary card-outline rounded-xl text-xs">

				{{-- HEADER --}}
				<div class="card-header d-flex align-items-center">
					<h3 class="card-title mb-0">Riwayat Pengajuan PH</h3>

					<button class="btn btn-primary btn-sm ml-auto rounded-xl" data-toggle="modal" data-target="#modalPengajuanPH">
						<i class="fas fa-plus"></i> Ajukan PH
					</button>
				</div>

				{{-- BODY --}}
				<div class="card-body">
					<div class="desktop-table">
						<table class="table-bordered table-hover table">
							<thead>
								<tr>
									<th>Tanggal PH</th>
									<th>Claim Date</th>
									<th>Status</th>
									<th>Reason</th>
									<th width="80">Aksi</th>
								</tr>
							</thead>

							<tbody>
								@forelse($requests as $r)
									@php
										if ($r->status === 'rejected') {
										    $label = 'Rejected';
										    $class = 'danger';
										} elseif ($r->status === 'cancelled') {
										    $label = 'Cancelled';
										    $class = 'secondary';
										} elseif ($r->hr_approved_at) {
										    $label = 'Approved HR';
										    $class = 'success';
										} elseif ($r->manager_approved_at) {
										    $label = 'Approved Atasan';
										    $class = 'info';
										} else {
										    $label = 'Pending';
										    $class = 'warning';
										}
									@endphp

									<tr>
										<td>
											{{ $r->holiday->holiday_date->format('d M Y') }} <br>
											<small>{{ $r->holiday->name }}</small>
										</td>

										<td>
											{{ $r->claim_date->format('d M Y') }}
										</td>

										<td>
											<span class="badge badge-{{ $class }}">
												{{ $label }}
											</span>
										</td>

										<td>
											{{ $r->reject_reason ?? '-' }}
										</td>

										<td class="text-center">
											@if ($r->status === 'pending')
												<form method="POST" action="{{ route('staff.public-holiday.destroy', $r->id) }}"
													class="d-inline form-delete">
													@csrf
													@method('DELETE')
													<button class="btn btn-danger btn-xs">
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
											Belum ada pengajuan PH
										</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>
					<div class="mobile-card">

						@forelse($requests as $r)
							@php
								if ($r->status === 'rejected') {
								    $label = 'Rejected';
								    $class = 'danger';
								} elseif ($r->status === 'cancelled') {
								    $label = 'Cancelled';
								    $class = 'secondary';
								} elseif ($r->hr_approved_at) {
								    $label = 'Approved HR';
								    $class = 'success';
								} elseif ($r->manager_approved_at) {
								    $label = 'Approved Atasan';
								    $class = 'info';
								} else {
								    $label = 'Pending';
								    $class = 'warning';
								}
							@endphp

							<div class="card shadow-sm">
								<div class="card-body p-1 mb-2">

									{{-- HEADER --}}
									<div class="d-flex justify-content-between align-items-center">
										<strong>
											🎉 {{ $r->holiday->holiday_date->format('d M Y') }}
										</strong>

										<span class="badge badge-{{ $class }}">
											{{ $label }}
										</span>
									</div>

									{{-- BODY --}}
									<div class="mt-2 text-sm">

										<p class="mb-1">
											<strong>Hari:</strong>
											{{ $r->holiday->name }}
										</p>

										<p class="mb-1">
											<strong>Claim:</strong>
											{{ $r->claim_date->format('d M Y') }}
										</p>

										@if ($r->reject_reason)
											<p class="text-danger mb-1">
												<strong>Alasan:</strong>
												{{ $r->reject_reason }}
											</p>
										@endif

									</div>

									{{-- ACTION --}}
									@if ($r->status === 'pending')
										<div class="mt-3">
											<form method="POST" action="{{ route('staff.public-holiday.destroy', $r->id) }}" class="form-delete">
												@csrf
												@method('DELETE')
												<button class="btn btn-danger btn-block btn-sm">
													🗑 Batalkan Pengajuan
												</button>
											</form>
										</div>
									@endif

								</div>
							</div>

						@empty
							<div class="text-muted text-center">
								Belum ada pengajuan PH
							</div>
						@endforelse

					</div>

				</div>

			</div>
		</div>
	</div>

	{{-- ============================= --}}
	{{-- MODAL PENGAJUAN PH --}}
	{{-- ============================= --}}

	<div class="modal fade" id="modalPengajuanPH" tabindex="-1">
		<div class="modal-dialog modal-md modal-dialog-centered">
			<form method="POST" action="{{ route('staff.public-holiday.store') }}">
				@csrf

				<div class="modal-content rounded-3xl border-0 shadow-lg">

					<div class="modal-header border-0 pb-0">
						<h5 class="modal-title font-weight-bold">
							<i class="fas fa-calendar-plus text-primary mr-2"></i>
							Pengajuan Public Holiday
						</h5>
						<button type="button" class="close" data-dismiss="modal">
							<span>&times;</span>
						</button>
					</div>

					<div class="modal-body pt-3">

						{{-- PILIH TANGGAL MERAH --}}
						<div class="form-group">
							<label class="font-weight-semibold">
								Pilih Tanggal Merah <span class="text-danger">*</span>
							</label>

							<select name="public_holiday_id" class="form-control rounded-xl" required>
								<option value="">-- Pilih --</option>

								@foreach ($holidays as $holiday)
									<option value="{{ $holiday->id }}">
										{{ $holiday->holiday_date->format('d M Y') }}
										- {{ $holiday->name }}
									</option>
								@endforeach
							</select>
						</div>

						{{-- CLAIM DATE --}}
						<div class="form-group">
							<label class="font-weight-semibold">
								Tanggal Claim PH <span class="text-danger">*</span>
							</label>

							<input type="date" name="claim_date" class="form-control rounded-xl" required>
						</div>

					</div>

					<div class="modal-footer border-0 pt-0">
						<button type="button" class="btn btn-light rounded-pill px-4" data-dismiss="modal">
							Batal
						</button>

						<button type="button" class="btn btn-primary rounded-pill px-4" id="btnSubmitPH">
							<i class="fas fa-paper-plane mr-1"></i> Kirim
						</button>
					</div>

				</div>
			</form>
		</div>
	</div>

	{{-- ============================= --}}
	{{-- SCRIPT --}}
	{{-- ============================= --}}

	<script>
		document.addEventListener('DOMContentLoaded', function() {

			const submitBtn = document.getElementById('btnSubmitPH');
			if (!submitBtn) return;

			submitBtn.addEventListener('click', function() {

				const form = submitBtn.closest('form');
				const holiday = form.querySelector('[name="public_holiday_id"]').value;
				const claimDate = form.querySelector('[name="claim_date"]').value;

				if (!holiday || !claimDate) {
					toastError('Semua field wajib diisi');
					return;
				}

				Swal.fire({
					title: 'Ajukan PH?',
					text: 'Pastikan data sudah benar.',
					icon: 'question',
					showCancelButton: true,
					confirmButtonText: 'Ya, Ajukan',
					cancelButtonText: 'Batal',
					confirmButtonColor: '#007bff',
					cancelButtonColor: '#6c757d'
				}).then((result) => {

					if (result.isConfirmed) {

						submitBtn.disabled = true;
						submitBtn.innerHTML = `
                    <span class="spinner-border spinner-border-sm"></span> Mengirim...
                `;

						form.submit();
					}

				});
			});

		});

		/* DELETE */
		document.querySelectorAll('.form-delete').forEach(form => {
			form.addEventListener('submit', function(e) {
				e.preventDefault();

				Swal.fire({
					title: 'Batalkan Pengajuan?',
					text: 'Pengajuan PH ini akan dibatalkan.',
					icon: 'warning',
					showCancelButton: true,
					confirmButtonText: 'Ya, Batalkan',
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
	</script>


@endsection
