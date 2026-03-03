@extends('layouts.app')

@section('title', 'Lembur')
@section('page-title', 'Lembur')

@section('content')
	<div class="card card-primary card-outline rounded-xl text-xs">
		<div class="card-header d-flex align-items-center">
			<h3 class="card-title mb-0">Riwayat Lembur</h3>

			<button class="btn btn-primary btn-sm ml-auto rounded-xl" data-toggle="modal" data-target="#modalOvertime">
				<i class="fas fa-plus"></i> Ajukan Lembur
			</button>
		</div>

		<div class="card-body">
			<table class="table-bordered table">
				<thead>
					<tr>
						<th>Tanggal</th>
						<th>Jam</th>
						<th>Status</th>
						<th width="70">Aksi</th>
					</tr>
				</thead>
				<tbody>
					@forelse($requests as $r)
						<tr>
							<td>{{ $r->date }}</td>
							<td>{{ $r->start_time }} - {{ $r->end_time }}</td>
							<td>
								<span
									class="badge badge-{{ $r->status == 'approved' ? 'success' : ($r->status == 'rejected' ? 'danger' : 'warning') }}">
									{{ ucfirst($r->status) }}
								</span>
							</td>
							<td class="text-center">
								@if ($r->status == 'pending')
									<form method="POST" action="{{ route('staff.overtime.destroy', $r->id) }}" class="form-delete">
										@csrf @method('DELETE')
										<button class="btn btn-danger btn-xs">
											<i class="fas fa-trash"></i>
										</button>
									</form>
								@else
									-
								@endif
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="4" class="text-muted text-center">
								Belum ada pengajuan lembur
							</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

	{{-- MODAL --}}
	<div class="modal fade" id="modalOvertime">
		<div class="modal-dialog">
			<form method="POST" action="{{ route('staff.overtime.store') }}">
				@csrf
				<div class="modal-content text-xs">
					<div class="modal-header">
						<h5 class="modal-title">Pengajuan Lembur</h5>
						<button class="close" data-dismiss="modal">&times;</button>
					</div>

					<div class="modal-body">
						<div class="form-group">
							<label>Tanggal</label>
							<input type="date" name="date" class="form-control" required>
						</div>

						<div class="form-group">
							<label>Jam Mulai</label>
							<input type="time" name="start_time" class="form-control" required>
						</div>

						<div class="form-group">
							<label>Jam Selesai</label>
							<input type="time" name="end_time" class="form-control" required>
						</div>

						<div class="form-group">
							<label>Alasan</label>
							<textarea name="reason" class="form-control" required></textarea>
						</div>
					</div>

					<div class="modal-footer">
						<button class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
						<button class="btn btn-primary btn-sm">
							<i class="fas fa-paper-plane"></i> Kirim
						</button>
					</div>
				</div>
			</form>
		</div>
	</div>
@endsection
