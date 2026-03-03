@extends('layouts.app')

@section('title', 'Izin / Sakit')
@section('page-title', 'Izin / Sakit')

@section('content')
	<div class="card card-primary card-outline rounded-xl text-xs">
		<div class="card-header d-flex align-items-center">
			<h3 class="card-title mb-0">Riwayat Izin / Sakit</h3>

			<button class="btn btn-primary btn-sm ml-auto rounded-xl" data-toggle="modal" data-target="#modalPermission">
				<i class="fas fa-plus"></i> Ajukan
			</button>
		</div>

		<div class="card-body">
			<table class="table-bordered table">
				<thead>
					<tr>
						<th>Tanggal</th>
						<th>Jenis</th>
						<th>Status</th>
						<th width="70">Aksi</th>
					</tr>
				</thead>
				<tbody>
					@forelse($requests as $r)
						<tr>
							<td>{{ $r->date }}</td>
							<td>{{ ucfirst($r->type) }}</td>
							<td>
								<span
									class="badge badge-{{ $r->status == 'approved' ? 'success' : ($r->status == 'rejected' ? 'danger' : 'warning') }}">
									{{ ucfirst($r->status) }}
								</span>
							</td>
							<td class="text-center">
								@if ($r->status == 'pending')
									<form method="POST" action="{{ route('staff.permission.destroy', $r->id) }}" class="form-delete">
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
								Belum ada pengajuan
							</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

	{{-- MODAL --}}
	<div class="modal fade" id="modalPermission">
		<div class="modal-dialog">
			<form method="POST" action="{{ route('staff.permission.store') }}" enctype="multipart/form-data">
				@csrf
				<div class="modal-content text-xs">
					<div class="modal-header">
						<h5 class="modal-title">Pengajuan Izin / Sakit</h5>
						<button class="close" data-dismiss="modal">&times;</button>
					</div>

					<div class="modal-body">
						<div class="form-group">
							<label>Jenis</label>
							<select name="type" class="form-control" id="typePermission" required>
								<option value="">-- Pilih --</option>
								<option value="izin">Izin</option>
								<option value="sakit">Sakit</option>
							</select>
						</div>

						<div class="form-group">
							<label>Tanggal</label>
							<input type="date" name="date" class="form-control" required>
						</div>

						<div class="form-group" id="reasonField">
							<label>Alasan</label>
							<textarea name="reason" class="form-control"></textarea>
						</div>

						<div class="form-group d-none" id="docField">
							<label>Surat Sakit</label>
							<input type="file" name="document" class="form-control">
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


	<script>
		document.getElementById('typePermission').addEventListener('change', function() {
			document.getElementById('reasonField').classList.toggle('d-none', this.value === 'sakit');
			document.getElementById('docField').classList.toggle('d-none', this.value !== 'sakit');
		});
	</script>

@endsection
