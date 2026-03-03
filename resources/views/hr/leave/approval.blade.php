@extends('layouts.app')

@section('title', 'Approval Cuti')
@section('page-title', 'Approval Pengajuan Cuti')

@section('content')

	<div class="card card-primary card-outline rounded-xl shadow-sm">
		<div class="card-header">
			<h3 class="card-title mb-0">
				Daftar Pengajuan Cuti
			</h3>
		</div>

		<div class="card-body">

			<table id="tblApprovalLeave" class="table-bordered table-hover table text-xs">
				<thead class="bg-gray-50">
					<tr>
					<tr>
						<th>Nama</th>
						<th>Tanggal</th>
						<th>Status</th>
						<th>Aksi</th>
					</tr>
					</tr>
				</thead>
				<tbody>

					@foreach ($requests as $r)
						@php
							if ($r->status === 'cancelled') {
							    $label = 'Cancelled';
							    $class = 'secondary';
							} elseif ($r->status === 'rejected') {
							    $label = 'Rejected';
							    $class = 'danger';
							} elseif ($r->hr_approved_at) {
							    $label = 'Approved';
							    $class = 'success';
							} else {
							    $label = 'Pending';
							    $class = 'warning';
							}
						@endphp

						<tr>
							<td>{{ $r->user->name }}</td>
							<td>{{ $r->start_date }} s/d {{ $r->end_date }}</td>
							<td>
								<span class="badge badge-{{ $class }}">
									{{ $label }}
								</span>
							</td>
							<td>
								@if (!$r->hr_approved_at && $r->status === 'pending')
									<form method="POST" action="{{ route('hr.leave.approve', $r->id) }}">
										@csrf
										<button class="btn btn-success btn-xs">
											<i class="fas fa-check"></i>
										</button>
									</form>
								@endif
								@if (!in_array($r->status, ['cancelled', 'rejected']))
									<button class="btn btn-danger btn-xs btn-cancel" data-id="{{ $r->id }}" data-name="{{ $r->user->name }}">
										<i class="fas fa-times"></i>
									</button>
								@endif
							</td>
						</tr>
					@endforeach

				</tbody>
			</table>

		</div>
	</div>
	<!-- Modal Cancel -->
	<div class="modal fade" id="cancelModal" tabindex="-1">
		<div class="modal-dialog">
			<form method="POST" id="cancelForm">
				@csrf
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Batalkan Cuti</h5>
						<button type="button" class="close" data-dismiss="modal">
							&times;
						</button>
					</div>
					<div class="modal-body">
						<p id="cancelText"></p>

						<div class="form-group">
							<label>Alasan Pembatalan</label>
							<textarea name="reject_reason" class="form-control" required></textarea>
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

		$(document).on('click', '.btn-cancel', function() {
			let id = $(this).data('id');
			let name = $(this).data('name');

			$('#cancelText').text(
				'Anda yakin ingin membatalkan cuti milik ' + name + '?'
			);

			$('#cancelForm').attr(
				'action',
				"{{ route('hr.leave.cancel', ':id') }}".replace(':id', id)
			);


			$('#cancelModal').modal('show');
		});
	</script>
@endpush
