@extends('layouts.app')

@section('title', 'Lembur')
@section('page-title', 'Lembur')

@section('content')
	<div class="card card-primary card-outline rounded-xl text-xs">
		<div class="card-header d-flex align-items-center">
			<h3 class="card-title mb-0">Pengajuan Lembur Bawahan</h3>

			<button class="btn btn-primary btn-sm ml-auto rounded-xl" data-toggle="modal" data-target="#modalOvertime">
				<i class="fas fa-plus"></i> Ajukan Lembur
			</button>
		</div>

		<div class="card-body">
			@if ($subordinates->isEmpty())
				<div class="alert alert-info mb-3">
					Anda belum memiliki bawahan langsung pada master karyawan, sehingga belum bisa mengajukan lembur.
				</div>
			@endif

			<table class="table-bordered table">
				<thead>
					<tr>
						<th>Karyawan</th>
						<th>Tanggal</th>
						<th>Jam</th>
						<th>Alasan</th>
						<th>Status</th>
						<th width="70">Aksi</th>
					</tr>
				</thead>
				<tbody>
					@forelse($requests as $r)
						<tr>
							<td>
								<div class="font-weight-bold">{{ $r->user->karyawan->nama_karyawan ?? $r->user->name }}</div>
								<div class="text-muted">{{ $r->user->username }}</div>
							</td>
							<td>{{ $r->date?->format('d M Y') ?? $r->date }}</td>
							<td>{{ $r->start_time }} - {{ $r->end_time }}</td>
							<td>{{ $r->reason }}</td>
							<td>
								<span
									class="badge badge-{{ $r->status == 'approved' ? 'success' : ($r->status == 'rejected' ? 'danger' : 'warning') }}">
									{{ $r->status == 'pending' ? 'Menunggu HR' : ucfirst($r->status) }}
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
							<td colspan="6" class="text-muted text-center">
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
							<label>Karyawan Lembur</label>
							<div class="rounded border p-2" style="max-height: 180px; overflow-y: auto;">
								@forelse ($subordinates as $employee)
									<div class="custom-control custom-checkbox mb-1">
										<input type="checkbox" name="employee_niks[]" value="{{ $employee->nik }}"
											class="custom-control-input" id="employee{{ $employee->nik }}">
										<label class="custom-control-label" for="employee{{ $employee->nik }}">
											{{ $employee->nama_karyawan }}
											<span class="text-muted">({{ $employee->nik }})</span>
										</label>
									</div>
								@empty
									<div class="text-muted">Tidak ada bawahan langsung.</div>
								@endforelse
							</div>
						</div>

						<div class="form-group">
							<label>Tanggal Lembur</label>
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
							<label>Pekerjaan / Alasan Lembur</label>
							<textarea name="reason" class="form-control" placeholder="Contoh: deploy aplikasi, stock opname, closing payroll" required></textarea>
						</div>
					</div>

					<div class="modal-footer">
						<button class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
						<button class="btn btn-primary btn-sm" {{ $subordinates->isEmpty() ? 'disabled' : '' }}>
							<i class="fas fa-paper-plane"></i> Kirim
						</button>
					</div>
				</div>
			</form>
		</div>
	</div>

	@push('scripts')
		<script>
		$(function () {

		function getOvertimeDuration() {
			const start = $('[name="start_time"]').val();
			const end   = $('[name="end_time"]').val();
			if (!start || !end) return null;

			const [sh, sm] = start.split(':').map(Number);
			const [eh, em] = end.split(':').map(Number);
			return (eh * 60 + em) - (sh * 60 + sm); // dalam menit
		}

		function showToast(message, type = 'warning') {
			// type: 'success' | 'warning' | 'danger'
			const icons = {
			success : 'fas fa-check-circle',
			warning : 'fas fa-exclamation-triangle',
			danger  : 'fas fa-times-circle',
			};

			$(document).Toasts('create', {
			title    : 'Perhatian',
			body     : message,
			icon     : icons[type] ?? icons.warning,
			class    : `bg-${type}`,
			autohide : true,
			delay    : 4000,
			});
		}

		// Validasi real-time saat jam diubah
		$('[name="start_time"], [name="end_time"]').on('change', function () {
			const diff = getOvertimeDuration();
			if (diff === null) return;

			if (diff > 240) {
			showToast('Durasi lembur melebihi batas maksimal 4 jam.', 'danger');
			} else if (diff < 60 && diff > 0) {
			showToast('Minimal durasi lembur adalah 1 jam.', 'warning');
			}
		});

		// Validasi saat form disubmit
		$('#modalOvertime form').on('submit', function (e) {
			const diff = getOvertimeDuration();

			if (diff === null || diff <= 0) {
			e.preventDefault();
			showToast('Jam selesai harus lebih besar dari jam mulai.', 'danger');
			return;
			}

			if (diff < 60) {
			e.preventDefault();
			showToast('Minimal durasi lembur adalah 1 jam.', 'warning');
			return;
			}

			if (diff > 240) {
			e.preventDefault();
			showToast('Durasi lembur melebihi batas maksimal 4 jam (240 menit).', 'danger');
			return;
			}
		});

		});
		</script>
		@endpush
@endsection
