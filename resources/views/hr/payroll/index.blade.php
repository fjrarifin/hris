@extends('layouts.app')

@section('title', 'Data Payroll')
@section('page-title', 'Data Payroll')

@section('content')
	<style>
		.payroll-badge {
			display: inline-flex;
			align-items: center;
			gap: 4px;
			border-radius: 999px;
			padding: 3px 9px;
			font-size: 11px;
			font-weight: 700;
			white-space: nowrap;
		}

		.payroll-action-menu {
			min-width: 190px;
			z-index: 1050;
		}

		.payroll-action-menu .dropdown-item {
			font-size: 12px;
			font-weight: 600;
			padding: 8px 12px;
		}

		.payroll-action-menu .dropdown-item i {
			width: 16px;
			margin-right: 8px;
			text-align: center;
		}
	</style>

	@if (session('success'))
		<div class="mb-3 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700">
			{{ session('success') }}
		</div>
	@endif

	<div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-4">
		<div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
			<div class="text-xs font-semibold uppercase text-slate-500">Total Payroll</div>
			<div class="text-2xl font-bold text-slate-900">{{ $summary['total'] }}</div>
		</div>
		<div class="rounded-lg border border-green-200 bg-white p-4 shadow-sm">
			<div class="text-xs font-semibold uppercase text-green-600">Approved</div>
			<div class="text-2xl font-bold text-green-700">{{ $summary['approved'] }}</div>
		</div>
		<div class="rounded-lg border border-blue-200 bg-white p-4 shadow-sm">
			<div class="text-xs font-semibold uppercase text-blue-600">Locked</div>
			<div class="text-2xl font-bold text-blue-700">{{ $summary['locked'] }}</div>
		</div>
		<div class="rounded-lg border border-amber-200 bg-white p-4 shadow-sm">
			<div class="text-xs font-semibold uppercase text-amber-600">Warning</div>
			<div class="text-2xl font-bold text-amber-700">{{ $summary['warning'] }}</div>
		</div>
	</div>

	<div class="mb-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
		<form method="GET" action="{{ route('hr.payroll.index') }}" class="grid grid-cols-1 items-end gap-3 md:grid-cols-5">
			<div>
				<label class="mb-1 block text-xs font-bold text-slate-600">Periode Awal</label>
				<input type="date" name="periode_start" value="{{ request('periode_start') }}"
					class="form-control form-control-sm">
			</div>
			<div>
				<label class="mb-1 block text-xs font-bold text-slate-600">Periode Akhir</label>
				<input type="date" name="periode_end" value="{{ request('periode_end') }}"
					class="form-control form-control-sm">
			</div>
			<div>
				<label class="mb-1 block text-xs font-bold text-slate-600">Approval</label>
				<select name="approval_status" class="form-control form-control-sm">
					<option value="">Semua</option>
					@foreach (['draft' => 'Draft', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
						<option value="{{ $key }}" @selected(request('approval_status') === $key)>{{ $label }}</option>
					@endforeach
				</select>
			</div>
			<div class="flex gap-2">
				<button type="submit" class="btn btn-primary btn-sm font-bold">
					<i class="fas fa-search mr-1"></i> Filter
				</button>
				<a href="{{ route('hr.payroll.index') }}" class="btn btn-secondary btn-sm font-bold">
					<i class="fas fa-redo mr-1"></i> Reset
				</a>
			</div>
			<div class="flex flex-wrap justify-end gap-2">
				<a href="{{ route('hr.payroll.history') }}" class="btn btn-outline-secondary btn-sm font-bold">
					<i class="fas fa-history mr-1"></i> History
				</a>
				<a href="{{ route('hr.payroll.email-template') }}" class="btn btn-outline-secondary btn-sm font-bold">
					<i class="fas fa-envelope-open-text mr-1"></i> Template
				</a>
				<a href="{{ route('hr.payroll.export', request()->query()) }}" class="btn btn-success btn-sm font-bold">
					<i class="fas fa-file-excel mr-1"></i> Export
				</a>
				<button type="button" onclick="syncKaryawan()" class="btn btn-info btn-sm font-bold">
					<i class="fas fa-sync mr-1"></i> Sync
				</button>
				<button type="button" onclick="blastEmail()" class="btn btn-warning btn-sm font-bold">
					<i class="fas fa-paper-plane mr-1"></i> Blast Email
				</button>
			</div>
		</form>
	</div>

	<div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
		<table id="tblPayroll" class="table-bordered table-striped table-hover table w-full text-xs">
			<thead class="bg-slate-50 text-slate-600">
				<tr>
					<th class="text-center">Periode</th>
					<th>Karyawan</th>
					<th class="text-right">Total Dibayarkan</th>
					<th class="text-center">Approval</th>
					<th class="text-center">Lock</th>
					<th class="text-center">Validasi</th>
					<th class="text-center">Email Log</th>
					<th class="text-center">Aksi</th>
				</tr>
			</thead>
			<tbody>
				@forelse ($payrolls as $r)
					@php
						$warnings = $r->validation_warnings ?? [];
						$warningText = collect($warnings['critical'] ?? [])
						    ->merge($warnings['warnings'] ?? [])
						    ->implode("\n");
					@endphp
					<tr>
						<td class="text-center font-semibold text-slate-700">
							{{ optional($r->periode_start)->format('d M Y') }}<br>
							<span class="text-slate-400">s/d</span><br>
							{{ optional($r->periode_end)->format('d M Y') }}
						</td>
						<td>
							<div class="font-bold text-slate-900">{{ $r->karyawan?->nama_karyawan ?? '-' }}</div>
							<div class="text-slate-500">NIK: {{ $r->karyawan_nik ?? '-' }}</div>
							<div class="text-slate-500">{{ $r->karyawan?->departement ?? '-' }} / {{ $r->karyawan?->unit ?? '-' }}</div>
						</td>
						<td class="text-right font-bold text-slate-900">Rp {{ number_format($r->total_dibayarkan, 0, ',', '.') }}</td>
						<td class="text-center">
							<span @class([
								'payroll-badge',
								'bg-slate-100 text-slate-700' => $r->approval_status === 'draft',
								'bg-amber-100 text-amber-700' => $r->approval_status === 'pending',
								'bg-green-100 text-green-700' => $r->approval_status === 'approved',
								'bg-red-100 text-red-700' => $r->approval_status === 'rejected',
							])>
								{{ strtoupper($r->approval_status ?? 'draft') }}
							</span>
						</td>
						<td class="text-center">
							<span class="payroll-badge {{ $r->is_locked ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }}">
								<i class="fas {{ $r->is_locked ? 'fa-lock' : 'fa-lock-open' }}"></i>
								{{ $r->is_locked ? 'LOCKED' : 'OPEN' }}
							</span>
						</td>
						<td class="text-center">
							<button type="button" title="{{ $warningText ?: 'Belum ada catatan' }}"
								class="payroll-badge border-0 {{ $r->validation_status === 'invalid' ? 'bg-red-100 text-red-700' : ($r->validation_status === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700') }}"
								onclick="payrollAction('{{ route('hr.payroll.validate', $r->id) }}', 'Validasi Payroll?', 'Validasi akan diperbarui.', false)">
								<i class="fas fa-exclamation-triangle"></i>
								{{ strtoupper($r->validation_status ?? 'unchecked') }}
							</button>
						</td>
						<td class="text-center text-slate-600">
							@if ($r->latestEmailLog)
								<div class="font-bold">{{ strtoupper($r->latestEmailLog->status) }}</div>
								<div>{{ $r->latestEmailLog->created_at?->format('d M H:i') }}</div>
							@else
								-
							@endif
						</td>
						<td class="text-center" style="width: 90px; white-space: nowrap;">
							<div class="dropdown">
								<button type="button" class="btn btn-primary btn-sm font-bold dropdown-toggle" data-toggle="dropdown"
									aria-haspopup="true" aria-expanded="false">
									<i class="fas fa-ellipsis-v mr-1"></i> Aksi
								</button>
								<div class="dropdown-menu dropdown-menu-right payroll-action-menu">
									<a href="{{ route('hr.payroll.show', $r->id) }}" class="dropdown-item">
										<i class="fas fa-eye text-blue-500"></i> Preview Slip
									</a>
									<a href="{{ route('hr.payroll.download', $r->id) }}" class="dropdown-item">
										<i class="fas fa-download text-green-500"></i> Download PDF
									</a>
									<div class="dropdown-divider"></div>
									<button type="button" class="dropdown-item"
										onclick="payrollAction('{{ route('hr.payroll.approve', $r->id) }}', 'Approve Payroll?', 'Payroll akan disetujui.')">
										<i class="fas fa-check text-emerald-500"></i> Approve
									</button>
									<button type="button" class="dropdown-item"
										onclick="rejectPayroll('{{ route('hr.payroll.reject', $r->id) }}')">
										<i class="fas fa-times text-red-500"></i> Reject
									</button>
									@if ($r->is_locked)
										<button type="button" class="dropdown-item"
											onclick="payrollAction('{{ route('hr.payroll.unlock', $r->id) }}', 'Buka Lock?', 'Payroll bisa diproses ulang setelah dibuka.')">
											<i class="fas fa-lock-open text-slate-700"></i> Unlock
										</button>
									@else
										<button type="button" class="dropdown-item"
											onclick="payrollAction('{{ route('hr.payroll.lock', $r->id) }}', 'Lock Payroll?', 'Payroll harus approved sebelum dikunci.')">
											<i class="fas fa-lock text-indigo-500"></i> Lock
										</button>
									@endif
									<div class="dropdown-divider"></div>
									<button type="button" class="dropdown-item"
										onclick="payrollAction('{{ route('hr.payroll.send-email', $r->id) }}', 'Kirim Ulang Slip Gaji?', 'Email real akan dikirim ke alamat karyawan jika payroll sudah approved, locked, dan valid.')">
										<i class="fas fa-envelope text-amber-500"></i> Kirim Ulang Email
									</button>
								</div>
							</div>
						</td>
					</tr>
				@empty
					<tr>
						<td colspan="8" class="py-8 text-center text-sm text-slate-500">Data payroll belum tersedia.</td>
					</tr>
				@endforelse
			</tbody>
		</table>
	</div>
@endsection

@push('scripts')
	<script>
		$(document).ready(function() {
			$('#tblPayroll').DataTable({
				responsive: true,
				autoWidth: false,
				pageLength: 10,
				sort: false,
				columnDefs: [{
					targets: 7,
					orderable: false,
					searchable: false
				}]
			});
		});

		function payrollAction(url, title, text, reload = true, data = {}) {
			const isLongAction = url.includes('/send-email') || url.includes('/blast-email') || url.includes('/sync');
			let loadingTitle = 'Memproses...';
			let loadingText = 'Mohon tunggu, proses sedang berjalan.';

			if (url.includes('/blast-email')) {
				loadingTitle = 'Mengirim Email Massal...';
				loadingText = 'Mohon tunggu, slip gaji sedang dikirim ke karyawan yang lolos validasi.';
			} else if (url.includes('/send-email')) {
				loadingTitle = 'Mengirim Email...';
				loadingText = 'Mohon tunggu, slip gaji sedang dikirim ke email karyawan.';
			} else if (url.includes('/sync')) {
				loadingTitle = 'Sinkronisasi Payroll...';
				loadingText = 'Mohon tunggu, data payroll sedang diambil dan diproses.';
			}

			Swal.fire({
				title,
				text,
				icon: 'question',
				showCancelButton: true,
				confirmButtonText: 'Ya',
				cancelButtonText: 'Batal',
			}).then((result) => {
				if (!result.isConfirmed) return;

				$.ajax({
					url,
					type: 'POST',
					beforeSend: function() {
						if (!isLongAction) return;

						Swal.fire({
							title: loadingTitle,
							text: loadingText,
							allowOutsideClick: false,
							allowEscapeKey: false,
							showConfirmButton: false,
							didOpen: () => {
								Swal.showLoading();
							}
						});
					},
					data: {
						_token: '{{ csrf_token() }}',
						...data
					},
					success: function(res) {
						if (res.status) {
							let message = res.message ?? 'Proses selesai.';

							if (res.data) {
								message += `<br><br>
									<div class="text-left">
										Terkirim: <b>${res.data.sent ?? 0}</b><br>
										Diblokir: <b>${res.data.blocked ?? 0}</b><br>
										Gagal: <b>${res.data.failed ?? 0}</b>
									</div>`;
							}

							Swal.fire({
								icon: 'success',
								title: 'Berhasil',
								html: message
							}).then(() => {
								if (reload) location.reload();
							});
						} else {
							Swal.fire('Gagal', res.error ?? 'Proses gagal.', 'error');
						}
					},
					error: function(xhr) {
						Swal.fire('Error', xhr.responseJSON?.error ?? 'Terjadi kesalahan.', 'error');
					}
				});
			});
		}

		function rejectPayroll(url) {
			Swal.fire({
				title: 'Tolak Payroll',
				input: 'textarea',
				inputLabel: 'Catatan',
				showCancelButton: true,
				confirmButtonText: 'Tolak',
				cancelButtonText: 'Batal',
			}).then((result) => {
				if (!result.isConfirmed) return;
				payrollAction(url, 'Konfirmasi Reject?', 'Payroll akan ditolak.', true, {
					notes: result.value ?? ''
				});
			});
		}

		function syncKaryawan() {
			payrollAction("{{ route('hr.payroll.sync') }}", 'Sync Payroll?', 'Data akan diambil dari Google Sheets.');
		}

		function blastEmail() {
			payrollAction("{{ route('hr.payroll.blast-email') }}", 'Blast Email Slip Gaji?', 'Email real akan dikirim untuk payroll periode terakhir yang sudah approved, locked, dan valid.');
		}
	</script>
@endpush
