@extends('layouts.app')

@section('title', 'Approval Cuti - Atasan Tidak Langsung')
@section('page-title', 'Approval Pengajuan Cuti Bawahan Tidak Langsung')

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
				Daftar Pengajuan Cuti Bawahan Tidak Langsung
			</h3>
		</div>

		<div class="card-body">
			<div class="desktop-table">
				<table id="tblApprovalLeave" class="table-bordered table-hover table text-xs">
					<thead class="bg-gray-50">
						<tr>
							<th>NIK</th>
							<th>Nama</th>
							<th>Jenis</th>
							<th>Tanggal</th>
							<th>Durasi</th>
							<th>Alasan</th>
							<th>Status</th>
							<th width="150">Aksi</th>
						</tr>
					</thead>
					<tbody>

						@forelse($leaveRequests as $r)
							@php
								if ($r->status === 'rejected') {
								    $label = 'Rejected';
								    $class = 'danger';
								} elseif ($r->second_manager_approved_at) {
								    $label = 'Approved Atasan TL';
								    $class = 'info';
								} elseif ($r->manager_approved_at) {
								    $label = 'Approved Atasan L';
								    $class = 'warning';
								} else {
								    $label = 'Pending';
								    $class = 'secondary';
								}
							@endphp
							<tr>
								<td>{{ $r->user->username }}</td>
								<td>{{ $r->user->karyawan->nama_karyawan ?? '-' }}</td>
								<td>
									{{ \App\Models\LeaveRequest::LEAVE_TYPES[$r->leave_type] ?? $r->leave_type }}
								</td>
								<td>
									{{ \Carbon\Carbon::parse($r->start_date)->format('Y-m-d') }} <br>
									s/d {{ \Carbon\Carbon::parse($r->end_date)->format('Y-m-d') }}
								</td>
								<td>
									{{ \Carbon\Carbon::parse($r->start_date)->diffInDays($r->end_date) + 1 }} hari
								</td>
								<td>{{ $r->reason ?? '-' }}</td>
								<td>
									<span class="badge badge-{{ $class }}">
										{{ $label }}
									</span>
								</td>
								<td class="text-center">

									@if ($r->status === 'pending' && $r->manager_approved_at && !$r->second_manager_approved_at)
										<form method="POST" action="{{ route('mgr.approval.leave.approve', $r->id) }}" class="d-inline">
											@csrf
											<button class="btn btn-success btn-xs" title="Approve">
												<i class="fas fa-check"></i>
											</button>
										</form>

										<form method="POST" action="{{ route('mgr.approval.leave.reject', $r->id) }}" class="d-inline">
											@csrf
											<button class="btn btn-danger btn-xs" title="Reject">
												<i class="fas fa-times"></i>
											</button>
										</form>
									@else
										<span class="text-muted">-</span>
									@endif

								</td>
							</tr>
						@empty
							<tr>
								<td colspan="8" class="text-muted text-center">Tidak ada pengajuan cuti yang perlu disetujui.</td>
							</tr>
						@endforelse

					</tbody>
				</table>
			</div>

			<!-- Mobile View -->
			<div class="mobile-card">
				@forelse($leaveRequests as $r)
					@php
						if ($r->status === 'rejected') {
						    $label = 'Rejected';
						    $class = 'danger';
						} elseif ($r->second_manager_approved_at) {
						    $label = 'Approved Atasan TL';
						    $class = 'info';
						} elseif ($r->manager_approved_at) {
						    $label = 'Approved Atasan L';
						    $class = 'warning';
						} else {
						    $label = 'Pending';
						    $class = 'secondary';
						}
					@endphp
					<div class="card">
						<div class="card-body">
							<div class="row">
								<div class="col-6">
									<strong>NIK:</strong> {{ $r->user->username }}
								</div>
								<div class="col-6">
									<strong>Nama:</strong> {{ $r->user->karyawan->nama_karyawan ?? '-' }}
								</div>
							</div>
							<div class="row mt-2">
								<div class="col-6">
									<strong>Jenis:</strong> {{ \App\Models\LeaveRequest::LEAVE_TYPES[$r->leave_type] ?? $r->leave_type }}
								</div>
								<div class="col-6">
									<strong>Status:</strong>
									<span class="badge badge-{{ $class }}">{{ $label }}</span>
								</div>
							</div>
							<div class="row mt-2">
								<div class="col-12">
									<strong>Tanggal:</strong> {{ \Carbon\Carbon::parse($r->start_date)->format('Y-m-d') }} s/d
									{{ \Carbon\Carbon::parse($r->end_date)->format('Y-m-d') }}
								</div>
							</div>
							<div class="row mt-2">
								<div class="col-12">
									<strong>Durasi:</strong> {{ \Carbon\Carbon::parse($r->start_date)->diffInDays($r->end_date) + 1 }} hari
								</div>
							</div>
							@if ($r->reason)
								<div class="row mt-2">
									<div class="col-12">
										<strong>Alasan:</strong> {{ $r->reason }}
									</div>
								</div>
							@endif
							@if ($r->status === 'pending' && $r->manager_approved_at && !$r->second_manager_approved_at)
								<div class="row mt-3">
									<div class="col-12">
										<form method="POST" action="{{ route('mgr.approval.leave.approve', $r->id) }}" class="d-inline">
											@csrf
											<button class="btn btn-success btn-sm" title="Approve">
												<i class="fas fa-check"></i> Approve
											</button>
										</form>

										<form method="POST" action="{{ route('mgr.approval.leave.reject', $r->id) }}" class="d-inline ml-2">
											@csrf
											<button class="btn btn-danger btn-sm" title="Reject">
												<i class="fas fa-times"></i> Reject
											</button>
										</form>
									</div>
								</div>
							@endif
						</div>
					</div>
				@empty
					<div class="card">
						<div class="card-body text-muted text-center">
							Tidak ada pengajuan cuti yang perlu disetujui.
						</div>
					</div>
				@endforelse
			</div>
		</div>
	</div>

@endsection
