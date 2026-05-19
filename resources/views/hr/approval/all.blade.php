@extends('layouts.app')

@section('title', 'Approval HR')
@section('page-title', 'Approval HR')

@section('content')
	<div class="card card-primary card-outline rounded-xl shadow-sm">
		<div class="card-header d-flex align-items-center">
			<h3 class="card-title mb-0">Pengajuan Menunggu Approval HR</h3>
		</div>

		<div class="card-body">
			<div class="table-responsive">
				<table id="tblHRApprovalAll" class="table-bordered table-hover table-sm table text-xs">
					<thead class="bg-gray-50">
						<tr>
							<th>Tipe</th>
							<th>Karyawan</th>
							<th>Detail</th>
							<th>Diajukan</th>
							<th width="120">Aksi</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($requests as $item)
							@php
								$r = $item->request;
								$type = $item->type;
								$typeLabel = match ($type) {
								    'leave' => 'Cuti',
								    'ph' => 'PH',
								    'permission' => 'Izin/Sakit',
								    'overtime' => 'Lembur',
								    default => strtoupper($type),
								};
								$badge = match ($type) {
								    'leave' => 'primary',
								    'ph' => 'info',
								    'permission' => 'secondary',
								    'overtime' => 'dark',
								    default => 'light',
								};
							@endphp
							<tr>
								<td><span class="badge badge-{{ $badge }}">{{ $typeLabel }}</span></td>
								<td>
									<div class="font-weight-bold">{{ $r->user->karyawan->nama_karyawan ?? $r->user->name }}</div>
									<div class="text-muted">{{ $r->user->username }}</div>
								</td>
								<td>
									@if ($type === 'leave')
										{{ \Carbon\Carbon::parse($r->start_date)->format('d M Y') }}
										-
										{{ \Carbon\Carbon::parse($r->end_date)->format('d M Y') }}
										<br>{{ \App\Models\LeaveRequest::LEAVE_TYPES[$r->leave_type] ?? $r->leave_type }}
									@elseif ($type === 'ph')
										{{ $r->holiday->name ?? 'Hari Libur' }}
										<br>Claim: {{ \Carbon\Carbon::parse($r->claim_date)->format('d M Y') }}
									@elseif ($type === 'permission')
										{{ $r->type === 'sakit' ? 'Sakit' : 'Izin Tidak Masuk' }}
										<br>{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}
										<br>{{ $r->reason ?: '-' }}
									@elseif ($type === 'overtime')
										{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}
										<br>{{ $r->start_time }} - {{ $r->end_time }}
										<br>{{ $r->reason }}
										<br><span class="text-muted">Diajukan oleh: {{ $r->requestedBy->name ?? '-' }}</span>
									@endif
								</td>
								<td>{{ $r->created_at?->format('d M Y H:i') }}</td>
								<td class="text-center">
									<form method="POST" action="{{ route('hr.approval.approve', [$type, $r->id]) }}" class="d-inline">
										@csrf
										<button class="btn btn-success btn-xs" title="Setujui">
											<i class="fas fa-check"></i>
										</button>
									</form>

									<button class="btn btn-danger btn-xs btn-reject" data-id="{{ $r->id }}"
										data-type="{{ $type }}" data-name="{{ $r->user->name }}" title="Tolak">
										<i class="fas fa-times"></i>
									</button>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="5" class="text-muted text-center">Tidak ada pengajuan menunggu approval HR.</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="modal fade" id="rejectModal" tabindex="-1">
		<div class="modal-dialog">
			<form method="POST" id="rejectForm">
				@csrf
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Tolak Pengajuan</h5>
						<button type="button" class="close" data-dismiss="modal">&times;</button>
					</div>
					<div class="modal-body">
						<p id="rejectText"></p>
						<div class="form-group">
							<label>Alasan Penolakan</label>
							<textarea name="reason" class="form-control" required></textarea>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
						<button type="submit" class="btn btn-danger">Konfirmasi</button>
					</div>
				</div>
			</form>
		</div>
	</div>
@endsection

@push('scripts')
	<script>
		$(document).ready(function() {
			$('#tblHRApprovalAll').DataTable({
				responsive: true,
				autoWidth: false,
				pageLength: 10
			});

			$(document).on('click', '.btn-reject', function() {
				const id = $(this).data('id');
				const type = $(this).data('type');
				const name = $(this).data('name');

				$('#rejectText').text('Anda yakin ingin menolak pengajuan milik ' + name + '?');
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
