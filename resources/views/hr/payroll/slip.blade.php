@extends('layouts.app')

@section('title', 'Preview Slip Gaji')
@section('page-title', 'Preview Slip Gaji')

@section('content')
	<style>
		.payroll-slip-preview {
			position: relative;
			max-width: 1120px;
			margin: 0 auto 28px;
			padding: 18px;
			background: #fff;
			border: 1px solid #d9dee7;
			box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
			font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
			color: #1a1a1a;
			overflow: hidden;
		}

		.payroll-slip-preview::before {
			content: "";
			position: absolute;
			inset: 18px;
			pointer-events: none;
			background-image: url("{{ asset('hompimplay_icon.png') }}");
			background-repeat: repeat;
			background-size: 96px auto;
			background-position: 12px 12px;
			opacity: 0.025;
		}

		.payroll-slip-preview table {
			width: 100%;
			border-collapse: collapse;
		}

		.payroll-slip-preview .slip-card,
		.payroll-slip-preview .tbl-benefit {
			position: relative;
			z-index: 1;
			width: 100%;
			border: 2px solid #1a1a1a;
			border-collapse: collapse;
			background: rgba(255, 255, 255, 0.96);
		}

		.payroll-slip-preview .slip-card td {
			padding: 0;
			vertical-align: top;
		}

		.payroll-slip-preview .header-company {
			font-size: 18px;
			font-weight: 800;
			letter-spacing: 0.5px;
			text-align: center;
			padding: 14px 16px;
			border-right: 2px solid #1a1a1a;
			border-bottom: 2px solid #1a1a1a;
			background: #f8fafc;
		}

		.payroll-slip-preview .header-logo {
			text-align: right;
			padding: 12px 18px;
			border-bottom: 2px solid #1a1a1a;
		}

		.payroll-slip-preview .tbl-info td,
		.payroll-slip-preview .tbl-bank td {
			padding: 4px 5px;
			font-size: 12px;
		}

		.payroll-slip-preview .info-label,
		.payroll-slip-preview .bank-label {
			color: #667085;
			white-space: nowrap;
		}

		.payroll-slip-preview .info-sep,
		.payroll-slip-preview .bank-sep {
			color: #667085;
			width: 12px;
		}

		.payroll-slip-preview .info-val,
		.payroll-slip-preview .bank-val {
			font-weight: 700;
			padding-left: 8px;
		}

		.payroll-slip-preview .periode-tag {
			display: inline-block;
			background: #1a1a1a;
			color: #fff;
			font-size: 12px;
			font-weight: 800;
			padding: 5px 12px;
			border-radius: 5px;
			margin-bottom: 12px;
			letter-spacing: 0.3px;
		}

		.payroll-slip-preview .tbl-absen td {
			padding: 4px 8px 4px 0;
			width: 16.66%;
			vertical-align: top;
		}

		.payroll-slip-preview .absen-label {
			display: block;
			font-size: 11px;
			color: #8a94a6;
		}

		.payroll-slip-preview .absen-val {
			display: block;
			font-size: 12px;
			font-weight: 800;
			color: #111827;
		}

		.payroll-slip-preview .section-title,
		.payroll-slip-preview .paid-title {
			font-size: 12px;
			font-weight: 800;
			letter-spacing: 5px;
			color: #475467;
			text-align: center;
			padding: 9px 0;
			background: #f8fafc;
			border-bottom: 1px solid #d9dee7;
		}

		.payroll-slip-preview .tbl-item td {
			padding: 4px 18px;
			font-size: 12px;
		}

		.payroll-slip-preview .item-name {
			width: 60%;
		}

		.payroll-slip-preview .item-cur {
			width: 8%;
			color: #8a94a6;
		}

		.payroll-slip-preview .item-amt {
			width: 32%;
			text-align: right;
			font-weight: 650;
		}

		.payroll-slip-preview .tbl-total {
			background: #f7f7f5;
		}

		.payroll-slip-preview .tbl-total td {
			padding: 12px 18px;
			font-size: 14px;
			font-weight: 800;
		}

		.payroll-slip-preview .total-cur {
			color: #667085;
			width: 8%;
		}

		.payroll-slip-preview .total-amt {
			text-align: right;
		}

		.payroll-slip-preview .thp-label {
			font-size: 11px;
			font-weight: 800;
			letter-spacing: 3px;
			color: #667085;
			text-transform: uppercase;
			margin-bottom: 6px;
		}

		.payroll-slip-preview .thp-amount {
			font-size: 28px;
			font-weight: 900;
			border-bottom: 2px solid #1a1a1a;
			padding-bottom: 5px;
		}

		.payroll-slip-preview .thp-rp {
			font-size: 16px;
			font-weight: 500;
			color: #667085;
			margin-right: 6px;
		}

		.payroll-slip-preview .tbl-benefit {
			margin-top: 14px;
		}

		.payroll-slip-preview .benefit-header td {
			font-size: 12px;
			font-weight: 800;
			letter-spacing: 5px;
			color: #475467;
			text-align: center;
			padding: 9px 0;
			border-bottom: 2px solid #1a1a1a;
			background: #f7f7f5;
		}

		.payroll-slip-preview .tbl-benefit td {
			padding: 7px 18px;
			font-size: 12px;
		}

		.payroll-slip-preview .br {
			border-right: 2px solid #1a1a1a;
		}

		.payroll-slip-preview .bb {
			border-bottom: 2px solid #1a1a1a;
		}

		@media (max-width: 768px) {
			.payroll-slip-preview {
				padding: 10px;
				overflow-x: auto;
			}

			.payroll-slip-preview .slip-card,
			.payroll-slip-preview .tbl-benefit {
				min-width: 900px;
			}
		}
	</style>

	@php
		$itemMap = $payroll->formatted_items->keyBy(function ($item) {
		    return $item->component->nama ?? $item->nama_item;
		});
		$critical = $validation['critical'] ?? [];
		$warnings = $validation['warnings'] ?? [];
	@endphp

	<div class="mb-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
		<div class="flex flex-wrap items-center justify-between gap-3">
			<div>
				<div class="text-sm font-bold text-slate-900">Workflow Payroll</div>
				<div class="mt-1 flex flex-wrap gap-2 text-xs font-bold">
					<span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">Approval:
						{{ strtoupper($payroll->approval_status ?? 'draft') }}</span>
					<span class="rounded-full {{ $payroll->is_locked ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }} px-3 py-1">
						{{ $payroll->is_locked ? 'LOCKED' : 'OPEN' }}
					</span>
					<span class="rounded-full {{ ($validation['status'] ?? 'unchecked') === 'invalid' ? 'bg-red-100 text-red-700' : (($validation['status'] ?? 'unchecked') === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700') }} px-3 py-1">
						VALIDASI: {{ strtoupper($validation['status'] ?? 'unchecked') }}
					</span>
				</div>
			</div>
			<div class="flex flex-wrap gap-2">
				<a href="{{ route('hr.payroll.index') }}" class="btn btn-secondary btn-sm font-bold">
					<i class="fas fa-arrow-left mr-1"></i> Kembali
				</a>
				<a href="{{ route('hr.payroll.download', $payroll->id) }}" class="btn btn-success btn-sm font-bold">
					<i class="fas fa-download mr-1"></i> PDF
				</a>
			</div>
		</div>

		@if (count($critical) || count($warnings))
			<div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
				@if (count($critical))
					<div class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
						<div class="mb-1 font-bold">Error Validasi</div>
						<ul class="mb-0 pl-4">
							@foreach ($critical as $message)
								<li>{{ $message }}</li>
							@endforeach
						</ul>
					</div>
				@endif
				@if (count($warnings))
					<div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700">
						<div class="mb-1 font-bold">Warning</div>
						<ul class="mb-0 pl-4">
							@foreach ($warnings as $message)
								<li>{{ $message }}</li>
							@endforeach
						</ul>
					</div>
				@endif
			</div>
		@endif

		@if ($payroll->emailLogs->isNotEmpty())
			<div class="mt-3 overflow-x-auto">
				<table class="table-bordered table-sm table text-xs">
					<thead>
						<tr>
							<th>Waktu</th>
							<th>Aksi</th>
							<th>Status</th>
							<th>Tujuan</th>
							<th>Catatan</th>
						</tr>
					</thead>
					<tbody>
						@foreach ($payroll->emailLogs->sortByDesc('created_at')->take(5) as $log)
							<tr>
								<td>{{ $log->created_at?->format('d M Y H:i') }}</td>
								<td>{{ strtoupper($log->action) }}</td>
								<td>{{ strtoupper($log->status) }}</td>
								<td>{{ $log->recipient_email ?? '-' }}</td>
								<td>{{ $log->notes ?? '-' }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		@endif
	</div>

	<div class="payroll-slip-preview">

		<table class="slip-card">

			{{-- ===== HEADER ===== --}}
			<tr>
				<td colspan="2" class="header-company">
					CV. 3 DETIK
				</td>
				{{-- <td width="38%" class="header-company">
                CV. 3 DETIK
            </td> --}}
				{{-- <td width="62%" class="header-logo">
                <img src="{{ public_path('hompimplay_icon.png') }}" style="max-height: 20pt;">
            </td> --}}
			</tr>

			{{-- ===== INFO KARYAWAN + ABSENSI ===== --}}
			<tr>
				<td class="br bb" style="padding: 8pt 10pt; vertical-align: top;">
					<table class="tbl-info">
						<tr>
							<td class="info-label">NIK</td>
							<td class="info-sep">:</td>
							<td class="info-val">{{ $payroll->karyawan->nik ?? '-' }}</td>
						</tr>
						<tr>
							<td class="info-label">Nama</td>
							<td class="info-sep">:</td>
							<td class="info-val">{{ $payroll->karyawan->nama_karyawan ?? '-' }}</td>
						</tr>
						<tr>
							<td class="info-label">Jabatan</td>
							<td class="info-sep">:</td>
							<td class="info-val">{{ $payroll->karyawan->jabatan ?? '-' }}</td>
						</tr>
						<tr>
							<td class="info-label">Departemen</td>
							<td class="info-sep">:</td>
							<td class="info-val">{{ $payroll->karyawan->departement ?? '-' }}</td>
						</tr>
						<tr>
							<td class="info-label">Unit</td>
							<td class="info-sep">:</td>
							<td class="info-val">{{ $payroll->karyawan->unit ?? '-' }}</td>
						</tr>
					</table>
				</td>
				<td class="bb" style="padding: 8pt 10pt; vertical-align: top;">
					<div class="periode-tag">
						{{ \Carbon\Carbon::parse($payroll->periode_start)->translatedFormat('d F') }}
						&mdash;
						{{ \Carbon\Carbon::parse($payroll->periode_end)->translatedFormat('d F Y') }}
					</div>
					<table class="tbl-absen">
						<tr>
							<td>
								<span class="absen-label">Kehadiran</span>
							</td>
							<td>
								<span class="absen-val">{{ $payroll->hadir }} Hari</span>
							</td>
							<td>
								<span class="absen-label">Libur</span>
							</td>
							<td>
								<span class="absen-val">{{ $payroll->libur }} Hari</span>
							</td>
							<td>
								<span class="absen-label">PH</span>
							</td>
							<td>
								<span class="absen-val">{{ $payroll->ph }} Hari</span>
							</td>
						</tr>
						<tr>
							<td>
								<span class="absen-label">Izin</span>
							</td>
							<td>
								<span class="absen-val">{{ $payroll->izin ?? 0 }} Hari</span>
							</td>
							<td>
								<span class="absen-label">Sakit (Surat)</span>
							</td>
							<td>
								<span class="absen-val">{{ $payroll->sakit_surat ?? 0 }} Hari</span>
							</td>
							<td>
								<span class="absen-label">Sakit (Tanpa Surat)</span>
							</td>
							<td>
								<span class="absen-val">{{ $payroll->sakit_tanpa_surat ?? 0 }} Hari</span>
							</td>
						</tr>
						<tr>
							<td>
								<span class="absen-label">Cuti Tahunan</span>
							</td>
							<td>
								<span class="absen-val">{{ $payroll->cuti_tahunan ?? 0 }} Hari</span>
							</td>
							<td>
								<span class="absen-label">Cuti Normatif</span>
							</td>
							<td>
								<span class="absen-val">{{ $payroll->cuti_normatif ?? 0 }} Hari</span>
							</td>
							<td>

							</td>
							<td>

							</td>
						</tr>
					</table>
				</td>
			</tr>

			{{-- ===== SECTION HEADER ===== --}}
			<tr>
				<td class="br bb section-title">P E N D A P A T A N</td>
				<td class="bb section-title">P O T O N G A N</td>
			</tr>

			{{-- ===== LINE ITEMS ===== --}}
			<tr>
				<td class="br bb" style="vertical-align: top; padding: 2pt 0;">
					<table class="tbl-item">
						<tr>
							<td class="item-name">Gaji Pokok</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ ($item = $payroll->getItemByComponentName('Gaji Pokok')) ? number_format($item->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Tunjangan Jabatan</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ ($item = $payroll->getItemByComponentName('Tunjangan Jabatan')) ? number_format($item->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Tunjangan Tidak Tetap</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ ($item = $payroll->getItemByComponentName('Tunjangan Tidak Tetap')) ? number_format($item->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Lembur</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ ($item = $payroll->getItemByComponentName('Lembur')) ? number_format($item->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Kekurangan Bulan Sebelumnya</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ ($item = $payroll->getItemByComponentName('Kekurangan Bulan Sebelumnya')) ? number_format($item->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Lain-lain</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ ($item = $payroll->getItemByComponentName('Lain-lain')) ? number_format($item->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Tunjangan BPJS Kesehatan</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ isset($itemMap['Pot. JKN Karyawan']) ? number_format($itemMap['Pot. JKN Karyawan']->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Tunjangan JHT Karyawan</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ isset($itemMap['Pot. JHT Karyawan']) ? number_format($itemMap['Pot. JHT Karyawan']->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Tunjangan JP Karyawan</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ isset($itemMap['Pot. JP Karyawan']) ? number_format($itemMap['Pot. JP Karyawan']->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">PPh 21</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ isset($itemMap['PPh21']) ? number_format($itemMap['PPh21']->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
					</table>
				</td>
				<td class="bb" style="vertical-align: top; padding: 4pt 0;">
					<table class="tbl-item">
						<tr>
							<td class="item-name">Potongan Sakit Tanpa Surat</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ isset($itemMap['Potongan Sakit Tanpa Surat']) ? number_format($itemMap['Potongan Sakit Tanpa Surat']->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Potongan Izin</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ isset($itemMap['Potongan Izin']) ? number_format($itemMap['Potongan Izin']->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Potongan Kasbon</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ isset($itemMap['Potongan Kasbon']) ? number_format($itemMap['Potongan Kasbon']->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Potongan Lain-lain</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ isset($itemMap['Lain-lain']) ? number_format($itemMap['Lain-lain']->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Potongan Denda Kehilangan Aset</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ isset($itemMap['Pot. Denda Kehilangan Aset']) ? number_format($itemMap['Pot. Denda Kehilangan Aset']->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Kelebihan Gaji</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ isset($itemMap['Kelebihan Gaji']) ? number_format($itemMap['Kelebihan Gaji']->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Pot. BPJS Karyawan</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ isset($itemMap['Pot. JKN Karyawan']) ? number_format($itemMap['Pot. JKN Karyawan']->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Pot. JHT Karyawan</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ isset($itemMap['Pot. JHT Karyawan']) ? number_format($itemMap['Pot. JHT Karyawan']->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">Pot. JP Karyawan</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ isset($itemMap['Pot. JP Karyawan']) ? number_format($itemMap['Pot. JP Karyawan']->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
						<tr>
							<td class="item-name">PPh 21</td>
							<td class="item-cur">Rp</td>
							<td class="item-amt">
								{{ isset($itemMap['PPh21']) ? number_format($itemMap['PPh21']->amount, 0, ',', '.') : '-' }}
							</td>
						</tr>
					</table>
				</td>
			</tr>

			{{-- ===== SUDAH DIBAYARKAN ===== --}}
			@if ($payroll->formatted_items->where('type', 'sudah_dibayarkan')->isNotEmpty() || (isset($payroll->thr) && $payroll->thr > 0))
				<tr>
					<td class="br bb" style="vertical-align: top; padding: 0;">
						<div class="paid-title">S u d a h &nbsp; D i b a y a r k a n</div>
						<table class="tbl-item" style="margin-top: 2pt;">
							@foreach ($payroll->formatted_items->where('type', 'sudah_dibayarkan') as $item)
								<tr>
									<td class="item-name">{{ $item->component->nama }}</td>
									<td class="item-cur">Rp</td>
									<td class="item-amt">{{ number_format($item->amount, 0, ',', '.') }}</td>
								</tr>
							@endforeach
							@if ($payroll->formatted_items->where('type', 'sudah_dibayarkan')->isEmpty())
								@if (isset($payroll->thr) && $payroll->thr > 0)
									<tr>
										<td class="item-name">THR</td>
										<td class="item-cur">Rp</td>
										<td class="item-amt">{{ number_format($payroll->thr, 0, ',', '.') }}</td>
									</tr>
								@else
									<tr>
										<td colspan="3" style="padding: 4pt 10pt; color: #bbb; font-style: italic; font-size: 6.5pt;">&mdash;
										</td>
									</tr>
								@endif
							@endif
						</table>
					</td>
					<td class="bb"></td>
				</tr>
			@endif

			{{-- ===== TOTALS ===== --}}
			<tr>
				<td class="br bb" style="padding: 0; vertical-align: top;">
					<table class="tbl-total">
						<tr>
							<td style="width: 60%;">Total Pendapatan</td>
							<td class="total-cur">Rp</td>
							<td class="total-amt">{{ number_format($payroll->total_pendapatan, 0, ',', '.') }}</td>
						</tr>
					</table>
				</td>
				<td class="bb" style="padding: 0; vertical-align: top;">
					<table class="tbl-total">
						<tr>
							<td style="width: 60%;">Total Potongan</td>
							<td class="total-cur">Rp</td>
							<td class="total-amt">
								{{ $payroll->total_potongan > 0 ? number_format($payroll->total_potongan, 0, ',', '.') : '—' }}
							</td>
						</tr>
					</table>
				</td>
			</tr>

			{{-- ===== FOOTER ===== --}}
			<tr>
				<td class="br" style="padding: 8pt 10pt; vertical-align: middle;">
					<table class="tbl-bank">
						<tr>
							<td class="bank-label">Nama Rekening</td>
							<td class="bank-sep">:</td>
							<td class="bank-val">{{ $payroll->karyawan->nama_karyawan ?? '-' }}</td>
						</tr>
						<tr>
							<td class="bank-label">Employee Bank</td>
							<td class="bank-sep">:</td>
							<td class="bank-val">{{ $payroll->karyawan->bank ?? '-' }}</td>
						</tr>
						<tr>
							<td class="bank-label">Account No</td>
							<td class="bank-sep">:</td>
							<td class="bank-val">{{ $payroll->karyawan->no_rekening ?? '-' }}</td>
						</tr>
					</table>
				</td>
				<td style="padding: 8pt 10pt; vertical-align: middle;">
					<div class="thp-label">Total Dibayarkan</div>
					<div class="thp-amount">
						<span class="thp-rp">Rp</span>{{ number_format($payroll->total_dibayarkan, 0, ',', '.') }}
					</div>
					<div style="border-top: 0.5pt solid #ccc; margin-top: 1pt;"></div>
				</td>
			</tr>

		</table>

		{{-- ===== BENEFIT LAINNYA ===== --}}
		<table class="tbl-benefit">
			<tr class="benefit-header">
				<td colspan="2">B E N E F I T &nbsp; L A I N N Y A</td>
			</tr>
			<tr>
				<td width="70%">Tunjangan BPJS Kesehatan Perusahaan</td>
				<td width="30%" style="text-align: right;">
					{{ isset($itemMap['JKN Perusahaan']) ? number_format($itemMap['JKN Perusahaan']->amount, 0, ',', '.') : '—' }}
				</td>
			</tr>
			<tr>
				<td>Tunjangan JHT Perusahaan</td>
				<td style="text-align: right;">
					{{ isset($itemMap['JHT Perusahaan']) ? number_format($itemMap['JHT Perusahaan']->amount, 0, ',', '.') : '—' }}
				</td>
			</tr>
			<tr>
				<td>Tunjangan JP Perusahaan</td>
				<td style="text-align: right;">
					{{ isset($itemMap['JP Perusahaan']) ? number_format($itemMap['JP Perusahaan']->amount, 0, ',', '.') : '—' }}
				</td>
			</tr>
			<tr>
				<td>Tunjangan JKK Perusahaan</td>
				<td style="text-align: right;">
					{{ isset($itemMap['JKK Perusahaan']) ? number_format($itemMap['JKK Perusahaan']->amount, 0, ',', '.') : '—' }}
				</td>
			</tr>
			<tr>
				<td>Tunjangan JKM Perusahaan</td>
				<td style="text-align: right;">
					{{ isset($itemMap['JKM Perusahaan']) ? number_format($itemMap['JKM Perusahaan']->amount, 0, ',', '.') : '—' }}
				</td>
			</tr>
		</table>

	</div>

@endsection
