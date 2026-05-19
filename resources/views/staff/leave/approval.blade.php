@extends('layouts.app')

@section('title', 'Approval Pengajuan')
@section('page-title', 'Persetujuan Pengajuan Tim')

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
		<div class="card-header">
			<h3 class="card-title mb-0">
				Daftar Pengajuan Tim
			</h3>
		</div>

		<div class="card-body">
			<div class="desktop-table">
				<table id="tblApprovalLeave" class="table-bordered table-hover table text-xs">
					<thead class="bg-gray-50">
						<tr>
							<th>NIK</th>
							<th>Nama</th>
							<th>Tipe</th>
							<th>Jenis</th>
							<th>Tanggal</th>
							<th>Durasi</th>
							<th>Alasan</th>
							<th>Status</th>
							<th>Alasan Penolakan</th>
							<th width="150">Aksi</th>
						</tr>
					</thead>
					<tbody>

						@forelse($requests as $r)
							@php
								// Determine request type
								$isLeave = get_class($r) === 'App\Models\LeaveRequest';
								$isPH = get_class($r) === 'App\Models\PublicHolidayRequest';
								$isPermission = get_class($r) === 'App\Models\EmployeePermission';

								if ($r->status === 'rejected') {
								    $label = 'Ditolak';
								    $class = 'danger';
								} elseif ($r->status === 'cancelled') {
								    $label = 'Dibatalkan';
								    $class = 'secondary';
								} elseif ($r->hr_approved_at ?? null) {
								    $label = 'Disetujui HR';
								    $class = 'success';
								} elseif ($r->manager_approved_at ?? null) {
								    $label = 'Disetujui Atasan';
								    $class = 'info';
								} else {
								    $label = 'Menunggu';
								    $class = 'warning';
								}
							@endphp
							<tr>
								<td>{{ $r->user->username }}</td>
								<td>{{ $r->user->karyawan->nama_karyawan ?? '-' }}</td>
								<td>
									@if ($isLeave)
										<span class="badge badge-primary">Cuti</span>
									@elseif($isPH)
										<span class="badge badge-secondary">PH</span>
									@elseif($isPermission)
										<span class="badge badge-info">Izin</span>
									@endif
								</td>
								<td>
									@if ($isLeave)
										{{ \App\Models\LeaveRequest::LEAVE_TYPES[$r->leave_type] ?? $r->leave_type }}
									@elseif($isPH)
										{{ $r->holiday->nama_hari ?? 'Hari Libur' }}
									@elseif($isPermission)
										{{ $r->type === 'sakit' ? 'Sakit' : 'Izin Tidak Masuk' }}
									@endif
								</td>
								<td>
									@if ($isLeave)
										{{ \Carbon\Carbon::parse($r->start_date)->format('Y-m-d') }} <br>
										s/d {{ \Carbon\Carbon::parse($r->end_date)->format('Y-m-d') }}
									@elseif($isPH)
										{{ \Carbon\Carbon::parse($r->claim_date)->format('Y-m-d') }}
									@elseif($isPermission)
										{{ \Carbon\Carbon::parse($r->date)->format('Y-m-d') }}
									@endif
								</td>
								<td>
									@if ($isLeave)
										{{ \Carbon\Carbon::parse($r->start_date)->diffInDays($r->end_date) + 1 }} hari
									@elseif($isPH)
										1 hari
									@elseif($isPermission)
										1 hari
									@endif
								</td>
								<td>{{ $r->reason ?? '-' }}</td>
								<td>
									<span class="badge badge-{{ $class }}">
										{{ $label }}
									</span>
								</td>
								<td>{{ $r->reject_reason ?? '-' }}</td>
								<td class="text-center">

									@if ($r->status === 'pending')
										@if ($isLeave)
											<form method="POST" action="{{ route('staff.approval.leave.approve', $r->id) }}" class="d-inline">
												@csrf
												<button class="btn btn-success btn-xs" title="Setujui">
													<i class="fas fa-check"></i>
												</button>
											</form>

											<form method="POST" action="{{ route('staff.approval.leave.reject', $r->id) }}" class="d-inline">
												@csrf
												<button class="btn btn-danger btn-xs" title="Tolak">
													<i class="fas fa-times"></i>
												</button>
											</form>
										@elseif($isPH)
											<form method="POST" action="{{ route('staff.approval.ph.approve', $r->id) }}" class="d-inline">
												@csrf
												<button class="btn btn-success btn-xs" title="Setujui">
													<i class="fas fa-check"></i>
												</button>
											</form>

											<form method="POST" action="{{ route('staff.approval.ph.reject', $r->id) }}" class="d-inline">
												@csrf
												<button class="btn btn-danger btn-xs" title="Tolak">
													<i class="fas fa-times"></i>
												</button>
											</form>
										@elseif($isPermission)
											<form method="POST" action="{{ route('staff.approval.permission.approve', $r->id) }}" class="d-inline">
												@csrf
												<button class="btn btn-success btn-xs" title="Setujui">
													<i class="fas fa-check"></i>
												</button>
											</form>

											<form method="POST" action="{{ route('staff.approval.permission.reject', $r->id) }}" class="d-inline">
												@csrf
												<button class="btn btn-danger btn-xs" title="Tolak">
													<i class="fas fa-times"></i>
												</button>
											</form>
										@endif
									@else
										<span class="text-muted">-</span>
									@endif

								</td>
							</tr>
						@empty
							<tr>
								<td colspan="10" class="text-muted text-center">
									Tidak ada pengajuan tim
								</td>
							</tr>
						@endforelse

					</tbody>
				</table>
			</div>
			<div class="mobile-card">

				@forelse($requests as $r)
					@php
						$isLeave = $r instanceof \App\Models\LeaveRequest;
						$isPH = $r instanceof \App\Models\PublicHolidayRequest;
						$isPermission = $r instanceof \App\Models\EmployeePermission;
					@endphp

					<div class="card shadow-sm">
						<div class="card-body">

							<h6 class="font-weight-bold mb-2">
								{{ $r->user->karyawan->nama_karyawan ?? '-' }}
							</h6>

							<p class="mb-1 text-sm">
								<strong>NIK:</strong> {{ $r->user->username }}
							</p>

							<p class="mb-1 text-sm">
								<strong>Tipe:</strong>
								@if ($isLeave)
									📅 Cuti
								@else
									🎉 PH
								@endif
							</p>

							<p class="mb-1 text-sm">
								<strong>Tanggal:</strong>
								@if ($isLeave)
									{{ \Carbon\Carbon::parse($r->start_date)->format('d M Y') }}
									-
									{{ \Carbon\Carbon::parse($r->end_date)->format('d M Y') }}
								@else
									{{ \Carbon\Carbon::parse($r->claim_date)->format('d M Y') }}
								@endif
							</p>

							<p class="mb-1 text-sm">
								<strong>Alasan:</strong> {{ $r->reason ?? '-' }}
							</p>

							<div class="mt-3">

								@if ($r->status === 'pending')
									@php
										$approveRoute = match (true) {
											$isLeave => route('staff.approval.leave.approve', $r->id),
											$isPH => route('staff.approval.ph.approve', $r->id),
											$isPermission => route('staff.approval.permission.approve', $r->id),
											default => route('staff.approval.permission.approve', $r->id),
										};
										$rejectRoute = match (true) {
											$isLeave => route('staff.approval.leave.reject', $r->id),
											$isPH => route('staff.approval.ph.reject', $r->id),
											$isPermission => route('staff.approval.permission.reject', $r->id),
											default => route('staff.approval.permission.reject', $r->id),
										};
									@endphp
									<form method="POST"
										action="{{ $approveRoute }}">
										@csrf
										<button class="btn btn-success btn-block btn-sm">
											✔ Setujui
										</button>
									</form>

									<form method="POST"
										action="{{ $rejectRoute }}"
										class="mt-2">
										@csrf
										<button class="btn btn-danger btn-block btn-sm">
											✖ Tolak
										</button>
									</form>
								@else
									<span class="badge badge-secondary">
										{{ ucfirst($r->status) }}
									</span>
								@endif

							</div>

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

@endsection

@push('scripts')
	<script>
		$(document).ready(function() {
			$('#tblApprovalLeave').DataTable({
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
		});
	</script>
@endpush
