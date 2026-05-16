@extends('layouts.app')

@section('title', 'Persetujuan HR')
@section('page-title', 'Persetujuan HR - ' . strtoupper($type))

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
	</style>

	<div class="card card-primary card-outline rounded-xl shadow-sm">

		<div class="card-header d-flex justify-content-between align-items-center">
			<h3 class="card-title mb-0">
				Daftar Pengajuan {{ strtoupper($type) }}
			</h3>

			<a href="{{ route('hr.approval.export', $type) }}" class="btn btn-sm btn-success ml-auto">
				<i class="fas fa-file-excel mr-1"></i>
				Ekspor Excel
			</a>
		</div>

		<div class="card-body">
			<div class="desktop-table">
				<table id="tblHRApproval" class="table-bordered table-hover table-sm table text-xs">

					<thead class="bg-gray-50">
						<tr>
							<th>Nama</th>

							@if ($type === 'leave')
								<th>Periode</th>
								<th>Jenis</th>
								<th>Keterangan</th>
							@endif

							@if ($type === 'ph')
								<th>Tanggal PH</th>
								<th>Claim</th>
							@endif

							<th>Status</th>
							<th width="120">Aksi</th>
						</tr>
					</thead>

					<tbody>
						@forelse($requests as $r)
							@php
								if ($r->status === 'cancelled') {
								    $label = 'Dibatalkan';
								    $class = 'secondary';
								} elseif ($r->status === 'rejected') {
								    $label = 'Ditolak';
								    $class = 'danger';
								} elseif ($r->hr_approved_at) {
								    $label = 'Disetujui HR';
								    $class = 'success';
								} elseif ($r->manager_approved_at) {
								    $label = $r->hr_approved_at ? 'Disetujui HR' : 'Menunggu HR';
								    $class = $r->hr_approved_at ? 'success' : 'warning';
								} else {
								    $label = 'Menunggu';
								    $class = 'warning';
								}
							@endphp

							<tr>
								<td>{{ $r->user->name }}</td>

								{{-- LEAVE --}}
								@if ($type === 'leave')
									<td>
										{{ \Carbon\Carbon::parse($r->start_date)->format('d M Y') }}
										-
										{{ \Carbon\Carbon::parse($r->end_date)->format('d M Y') }}
									</td>
									<td>
										{{ \App\Models\LeaveRequest::LEAVE_TYPES[$r->leave_type] ?? $r->leave_type }}
									</td>
									<td>{{ $r->reason }}</td>
								@endif

								{{-- PH --}}
								@if ($type === 'ph')
									<td>
										{{ $r->holiday->holiday_date->format('d M Y') }}
										<br>
										<small class="text-muted">
											{{ $r->holiday->name }}
										</small>
									</td>
									<td>
										{{ \Carbon\Carbon::parse($r->claim_date)->format('d M Y') }}
									</td>
								@endif

								<td>
									<span class="badge badge-{{ $class }}">
										{{ $label }}
									</span>
								</td>

								<td class="text-center">

									{{-- APPROVE --}}
									@if (!$r->hr_approved_at && $r->manager_approved_at && !in_array($r->status, ['rejected', 'cancelled']))
										<form method="POST" action="{{ route('hr.approval.approve', [$type, $r->id]) }}" class="d-inline">
											@csrf
											<button class="btn btn-success btn-xs" title="Setujui">
												<i class="fas fa-check"></i>
											</button>
										</form>
									@endif

									{{-- REJECT --}}
									@if (!$r->hr_approved_at && $r->manager_approved_at && !in_array($r->status, ['rejected', 'cancelled']))
										<button class="btn btn-danger btn-xs btn-reject" data-id="{{ $r->id }}"
											data-type="{{ $type }}" data-name="{{ $r->user->name }}" title="Tolak">
											<i class="fas fa-times"></i>
										</button>
									@endif

								</td>
							</tr>

						@empty
							<tr>
								<td colspan="6" class="text-muted text-center">
									Tidak ada pengajuan
								</td>
							</tr>
						@endforelse
					</tbody>

				</table>
			</div>
			<div class="mobile-card">

				@forelse($requests as $r)

					@php
						$isLeave = $type === 'leave';
						$isPH = $type === 'ph';

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
						    $label = 'Menunggu HR';
						    $class = 'warning';
						} else {
						    $label = 'Menunggu';
						    $class = 'warning';
						}
					@endphp

					<div class="card mb-2 shadow-sm">
						<div class="card-body p-2">

							{{-- HEADER --}}
							<div class="d-flex justify-content-between align-items-center">
								<strong>
									{{ $r->user->name }}
								</strong>

								<span class="badge badge-{{ $class }}">
									{{ $label }}
								</span>
							</div>

							{{-- BODY --}}
							<div class="mt-2 text-sm">

								@if ($isLeave)
									<p class="mb-1">
										<strong>Periode:</strong><br>
										{{ \Carbon\Carbon::parse($r->start_date)->format('d M Y') }}
										-
										{{ \Carbon\Carbon::parse($r->end_date)->format('d M Y') }}
									</p>

									<p class="mb-1">
										<strong>Jenis:</strong>
										{{ \App\Models\LeaveRequest::LEAVE_TYPES[$r->leave_type] ?? $r->leave_type }}
									</p>

									@if ($r->reason)
										<p class="mb-1">
											<strong>Keterangan:</strong>
											{{ $r->reason }}
										</p>
									@endif
								@endif


								@if ($isPH)
									<p class="mb-1">
										<strong>PH:</strong>
										{{ $r->holiday->holiday_date->format('d M Y') }}
									</p>

									<p class="mb-1">
										<strong>Claim:</strong>
										{{ $r->claim_date->format('d M Y') }}
									</p>

									<p class="mb-1">
										<strong>Hari:</strong>
										{{ $r->holiday->name }}
									</p>
								@endif

							</div>

							{{-- ACTION --}}
							@if (!$r->hr_approved_at && $r->manager_approved_at && !in_array($r->status, ['rejected', 'cancelled']))
								<div class="d-flex mt-2 gap-2">
									<form method="POST" action="{{ route('hr.approval.approve', [$type, $r->id]) }}" class="mr-2">
										@csrf
										<button class="btn btn-success btn-xs">
											<i class="fas fa-check"></i>
										</button>
									</form>

									@if (!in_array($r->status, ['rejected', 'cancelled']))
										<button class="btn btn-danger btn-xs btn-reject" data-id="{{ $r->id }}"
											data-type="{{ $type }}" data-name="{{ $r->user->name }}">
											<i class="fas fa-times"></i>
										</button>
									@endif
								</div>
							@endif

						</div>
					</div>

				@empty
					<div class="text-muted text-center">
						Tidak ada pengajuan
					</div>
				@endforelse

			</div>

		</div>
	</div>

	{{-- ================= MODAL REJECT ================= --}}
	<div class="modal fade" id="rejectModal" tabindex="-1">
		<div class="modal-dialog">
			<form method="POST" id="rejectForm">
				@csrf
				<div class="modal-content">

					<div class="modal-header">
						<h5 class="modal-title">Tolak Pengajuan</h5>
						<button type="button" class="close" data-dismiss="modal">
							&times;
						</button>
					</div>

					<div class="modal-body">

						<p id="rejectText"></p>

						<div class="form-group">
							<label>Alasan Penolakan</label>
							<textarea name="reason" class="form-control" required></textarea>
						</div>

					</div>

					<div class="modal-footer">
						<button class="btn btn-secondary" data-dismiss="modal">
							Batal
						</button>
						<button type="submit" class="btn btn-danger">
							Konfirmasi
						</button>
					</div>

				</div>
			</form>
		</div>
	</div>

@endsection


@push('scripts')
	<script>
		$(document).ready(function() {

			$('#tblHRApproval').DataTable({
				responsive: true,
				autoWidth: false,
				pageLength: 10,
				language: {
					search: "Cari:",
					lengthMenu: "Tampilkan _MENU_ data",
					zeroRecords: "Tidak ada data",
					info: "Menampilkan _PAGE_ dari _PAGES_",
					paginate: {
						next: "Selanjutnya",
						previous: "Sebelumnya"
					}
				}
			});

			// REJECT MODAL
			$(document).on('click', '.btn-reject', function() {

				let id = $(this).data('id');
				let type = $(this).data('type');
				let name = $(this).data('name');

				$('#rejectText').text(
					'Anda yakin ingin menolak pengajuan milik ' + name + '?'
				);

				$('#rejectForm').attr(
					'action',
					"{{ route('hr.approval.reject', ['TYPE', 'ID']) }}"
					.replace('TYPE', type)
					.replace('ID', id)
				);

				$('#rejectModal').modal('show');
			});

		});
	</script>
@endpush
