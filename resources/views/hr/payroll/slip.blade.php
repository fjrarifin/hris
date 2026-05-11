@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard HR')

@section('content')
	<style>
		body {
			font-family: Arial;
			font-size: 12px;
		}

		.container {
			border: 2px solid #000;
			padding: 10px;
		}

		.header {
			display: flex;
			justify-content: space-between;
			border-bottom: 2px solid #000;
			padding-bottom: 10px;
		}

		.title {
			font-size: 20px;
			font-weight: bold;
		}

		.row {
			display: flex;
			margin-top: 10px;
		}

		.col {
			width: 50%;
		}

		.box {
			border: 1px solid #000;
			margin-top: 10px;
		}

		.box-title {
			text-align: center;
			font-weight: bold;
			border-bottom: 1px solid #000;
			padding: 5px;
			letter-spacing: 3px;
		}

		table {
			width: 100%;
			border-collapse: collapse;
		}

		td {
			padding: 4px;
		}

		.right {
			text-align: right;
		}

		.bold {
			font-weight: bold;
		}

		.total {
			border-top: 2px solid #000;
			font-weight: bold;
		}

		.grand-total {
			text-align: center;
			margin-top: 15px;
			font-size: 18px;
			font-weight: bold;
		}

		.footer {
			border-top: 2px solid #000;
			margin-top: 10px;
			padding-top: 10px;
		}
	</style>

	<div class="container">

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
			@if ($payroll->items->where('type', 'sudah_dibayarkan')->isNotEmpty() || (isset($payroll->thr) && $payroll->thr > 0))
				<tr>
					<td class="br bb" style="vertical-align: top; padding: 0;">
						<div class="paid-title">S u d a h &nbsp; D i b a y a r k a n</div>
						<table class="tbl-item" style="margin-top: 2pt;">
							@foreach ($payroll->items->where('type', 'sudah_dibayarkan') as $item)
								<tr>
									<td class="item-name">{{ $item->component->nama }}</td>
									<td class="item-cur">Rp</td>
									<td class="item-amt">{{ number_format($item->amount, 0, ',', '.') }}</td>
								</tr>
							@endforeach
							@if ($payroll->items->where('type', 'sudah_dibayarkan')->isEmpty())
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
							<td class="bank-label">Account Name</td>
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
