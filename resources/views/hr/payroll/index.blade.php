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

		.payroll-card {
			border: 1px solid #e5e7eb;
			border-top: 3px solid #3b82f6;
			border-radius: 1.5rem;
			background: #fff;
			box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
		}

		.payroll-toolbar-menu {
			min-width: 230px;
			border: 1px solid #e5e7eb;
			border-radius: 16px;
			padding: 8px;
			box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
			z-index: 1060;
		}

		.payroll-toolbar-menu .dropdown-item {
			border-radius: 10px;
			font-size: 12px;
			font-weight: 700;
			padding: 9px 12px;
		}

		.payroll-toolbar-menu .dropdown-item i {
			width: 16px;
			margin-right: 8px;
			text-align: center;
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
		<div class="payroll-card p-4">
			<div class="text-xs font-semibold uppercase text-slate-500">Total Payroll</div>
			<div class="text-2xl font-bold text-slate-900">{{ $summary['total'] }}</div>
		</div>
		<div class="payroll-card p-4">
			<div class="text-xs font-semibold uppercase text-green-600">Approved</div>
			<div class="text-2xl font-bold text-green-700">{{ $summary['approved'] }}</div>
		</div>
		<div class="payroll-card p-4">
			<div class="text-xs font-semibold uppercase text-blue-600">Locked</div>
			<div class="text-2xl font-bold text-blue-700">{{ $summary['locked'] }}</div>
		</div>
		<div class="payroll-card p-4">
			<div class="text-xs font-semibold uppercase text-amber-600">Warning</div>
			<div class="text-2xl font-bold text-amber-700">{{ $summary['warning'] }}</div>
		</div>
	</div>

	<div class="payroll-card mb-4 p-4">
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
			<div class="flex justify-end">
				<div class="dropdown">
					<button type="button" class="btn btn-primary btn-sm font-bold dropdown-toggle" data-toggle="dropdown"
						aria-haspopup="true" aria-expanded="false">
						<i class="fas fa-bars mr-1"></i> Menu Payroll
					</button>
					<div class="dropdown-menu dropdown-menu-right payroll-toolbar-menu">
						<a href="{{ route('hr.payroll.history') }}" class="dropdown-item">
							<i class="fas fa-history text-slate-500"></i> History Payroll
						</a>
						<a href="{{ route('hr.payroll.email-template') }}" class="dropdown-item">
							<i class="fas fa-envelope-open-text text-blue-500"></i> Template Email
						</a>
						<a href="{{ route('hr.payroll.export', request()->query()) }}" class="dropdown-item">
							<i class="fas fa-file-excel text-green-500"></i> Export Excel
						</a>
						<div class="dropdown-divider"></div>
						<button type="button" onclick="syncKaryawan()" class="dropdown-item">
							<i class="fas fa-sync text-cyan-500"></i> Sync Google Sheet
						</button>
						<button type="button" onclick="blastEmail()" class="dropdown-item">
							<i class="fas fa-paper-plane text-amber-500"></i> Blast Email Slip
						</button>
					</div>
				</div>
			</div>
		</form>
	</div>

	<div class="payroll-card p-4">
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

		function escapeHtml(value) {
			return String(value ?? '').replace(/[&<>"']/g, function(char) {
				return {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#039;'
				} [char];
			});
		}

		function syncResultHtml(message, data) {
			const periods = Array.isArray(data.periods) && data.periods.length ?
				data.periods.map((period) => escapeHtml(period)).join('<br>') :
				escapeHtml(data.period_label ?? '-');
			const errors = Array.isArray(data.errors) ? data.errors : [];
			const errorPreview = errors.length ?
				`<div class="mt-3 rounded-lg border border-red-100 bg-red-50 p-3 text-xs text-red-700">
					<div class="mb-1 font-bold">Catatan error</div>
					${errors.slice(0, 5).map((error) => `<div>${escapeHtml(error)}</div>`).join('')}
					${errors.length > 5 ? `<div class="mt-1 font-semibold">+${errors.length - 5} error lainnya</div>` : ''}
				</div>` :
				'';

			return `<div class="text-left">
				<div class="mb-3 text-sm font-semibold text-slate-700">${escapeHtml(message)}</div>
				<div class="mb-3 rounded-lg border border-blue-100 bg-blue-50 p-3">
					<div class="text-xs font-bold uppercase text-blue-600">Periode Sync</div>
					<div class="text-sm font-bold text-slate-800">${periods}</div>
				</div>
				<div class="grid grid-cols-2 gap-2 text-center">
					<div class="rounded-lg border border-slate-200 bg-white p-3">
						<div class="text-xs font-bold uppercase text-slate-500">Data Ditarik</div>
						<div class="text-xl font-bold text-slate-900">${data.pulled ?? 0}</div>
					</div>
					<div class="rounded-lg border border-green-200 bg-green-50 p-3">
						<div class="text-xs font-bold uppercase text-green-600">Tersimpan</div>
						<div class="text-xl font-bold text-green-700">${data.inserted ?? 0}</div>
					</div>
					<div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
						<div class="text-xs font-bold uppercase text-amber-600">Duplikasi</div>
						<div class="text-xl font-bold text-amber-700">${data.duplicated ?? data.skipped ?? 0}</div>
					</div>
					<div class="rounded-lg border border-red-200 bg-red-50 p-3">
						<div class="text-xs font-bold uppercase text-red-600">Error</div>
						<div class="text-xl font-bold text-red-700">${data.failed ?? errors.length}</div>
					</div>
				</div>
				${errorPreview}
			</div>`;
		}

		function emailResultHtml(message, data) {
			return `${escapeHtml(message)}<br><br>
				<div class="text-left">
					Terkirim: <b>${data.sent ?? 0}</b><br>
					Diblokir: <b>${data.blocked ?? 0}</b><br>
					Gagal: <b>${data.failed ?? 0}</b>
				</div>`;
		}

		function actionResultHtml(url, res) {
			const message = res.message ?? 'Proses selesai.';

			if (!res.data) {
				return escapeHtml(message);
			}

			if (url.includes('/sync')) {
				return syncResultHtml(message, res.data);
			}

			if (url.includes('/blast-email')) {
				return emailResultHtml(message, res.data);
			}

			return escapeHtml(message);
		}

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
							Swal.fire({
								icon: 'success',
								title: 'Berhasil',
								html: actionResultHtml(url, res),
								width: url.includes('/sync') ? 560 : undefined
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
