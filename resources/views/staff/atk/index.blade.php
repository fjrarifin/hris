@extends('layouts.app')

@section('title', 'Pengajuan ATK')
@section('page-title', 'Pengajuan ATK')

@section('content')
	<div class="card card-primary card-outline rounded-xl text-xs">
		<div class="card-header d-flex align-items-center">
			<h3 class="card-title mb-0">Riwayat Pengajuan ATK</h3>

			<button class="btn btn-primary btn-sm ml-auto rounded-xl" data-toggle="modal" data-target="#modalAtk">
				<i class="fas fa-plus"></i> Ajukan ATK
			</button>
		</div>

		<div class="card-body">
			<table class="table-bordered table">
				<thead>
					<tr>
						<th>Barang</th>
						<th>Qty</th>
						<th>Status</th>
						<th width="70">Aksi</th>
					</tr>
				</thead>
				<tbody>
					@forelse($requests as $r)
						<tr>
							<td>{{ $r->item_name }}</td>
							<td>{{ $r->quantity }}</td>
							<td>
								<span
									class="badge badge-{{ $r->status == 'approved' ? 'success' : ($r->status == 'rejected' ? 'danger' : 'warning') }}">
									{{ ucfirst($r->status) }}
								</span>
							</td>
							<td class="text-center">
								@if ($r->status === 'pending')
									<form method="POST" action="{{ route('staff.atk.destroy', $r->id) }}" class="form-delete">
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
								Belum ada pengajuan ATK
							</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

	{{-- MODAL --}}
	<div class="modal fade" id="modalAtk">
		<div class="modal-dialog">
			<form method="POST" action="{{ route('staff.atk.store') }}">
				@csrf
				<div class="modal-content text-xs">
					<div class="modal-header">
						<h5 class="modal-title">
							<i class="fas fa-box"></i> Pengajuan ATK
						</h5>
						<button type="button" class="close" data-dismiss="modal">&times;</button>
					</div>

					<div class="modal-body">
						<div class="form-group">
							<label>Nama Barang</label>
							<input type="text" name="item_name" class="form-control" required>
						</div>

						<div class="form-group">
							<label>Jumlah</label>
							<input type="number" name="quantity" class="form-control" min="1" required>
						</div>

						<div class="form-group">
							<label>Catatan</label>
							<textarea name="note" class="form-control"></textarea>
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
