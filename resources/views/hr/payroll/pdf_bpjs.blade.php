<!DOCTYPE html>
<html>

<head>
	<title>Slip Gaji</title>
	<style>
		@page {
			size: A5 landscape;
			margin: 7mm 8mm 7mm 8mm;
		}

		* {
			box-sizing: border-box;
		}

		body {
			font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
			font-size: 10px;
			color: #1a1a1a;
			margin: 0;
			padding: 0;
		}

		table {
			border-collapse: collapse;
		}

		/* ===== OUTER CARD ===== */
		.slip-card {
			width: 100%;
			border: 1pt solid #1a1a1a;
			border-collapse: collapse;
		}

		.slip-card td {
			padding: 0;
			vertical-align: top;
		}

		/* ===== HEADER ===== */
		.header-company {
			font-size: 12pt;
			font-weight: 700;
			letter-spacing: 0.5pt;
			text-align: center;
			padding: 8pt 8pt;
			vertical-align: middle;
			border-right: 1pt solid #1a1a1a;
			border-bottom: 1pt solid #1a1a1a;
		}

		.header-logo {
			text-align: right;
			padding: 6pt 10pt;
			vertical-align: middle;
			border-bottom: 1pt solid #1a1a1a;
		}

		/* ===== INFO KARYAWAN ===== */
		.tbl-info {
			width: 100%;
			border-collapse: collapse;
		}

		.tbl-info td {
			padding: 1.5pt 2pt;
			vertical-align: top;
			font-size: 5pt;
		}

		.info-label {
			color: #666;
			white-space: nowrap;
			padding-right: 2pt;
		}

		.info-sep {
			color: #666;
			width: 6pt;
		}

		.info-val {
			font-weight: 600;
			padding-left: 4pt;
		}

		/* ===== PERIODE TAG ===== */
		.periode-tag {
			display: inline-block;
			background: #1a1a1a;
			color: #fff;
			font-size: 6pt;
			font-weight: 700;
			padding: 1.5pt 5pt;
			border-radius: 2pt;
			margin-bottom: 6pt;
			letter-spacing: 0.3pt;
		}

		/* ===== ABSENSI GRID ===== */
		.tbl-absen {
			width: 100%;
			border-collapse: collapse;
		}

		.tbl-absen td {
			padding: 1.5pt 4pt 1.5pt 0;
			width: 33.33%;
			vertical-align: top;
		}

		.absen-label {
			font-size: 5pt;
			color: #888;
			display: block;
		}

		.absen-val {
			font-size: 6pt;
			font-weight: 600;
			display: block;
		}

		/* ===== SECTION HEADER ===== */
		.section-title {
			font-size: 6pt;
			font-weight: 700;
			letter-spacing: 2.5pt;
			color: #555;
			text-align: center;
			padding: 4pt 0;
			border-bottom: 1pt solid #ddd;
		}

		/* ===== LINE ITEMS ===== */
		.tbl-item {
			width: 100%;
			border-collapse: collapse;
		}

		.tbl-item td {
			padding: 1pt 10pt;
			vertical-align: top;
			font-size: 5pt;
		}

		.item-name {
			width: 60%;
		}

		.item-cur {
			width: 8%;
			color: #888;
		}

		.item-amt {
			width: 32%;
			text-align: right;
		}

		.item-empty {
			color: #bbb;
		}

		/* ===== SUDAH DIBAYARKAN ===== */
		.paid-title {
			font-size: 6pt;
			font-weight: 700;
			letter-spacing: 2pt;
			color: #555;
			text-align: center;
			padding: 4pt 0;
			border-bottom: 1pt solid #eee;
		}

		/* ===== TOTAL ===== */
		.tbl-total {
			width: 100%;
			border-collapse: collapse;
			background: #f7f7f5;
		}

		.tbl-total td {
			padding: 5pt 10pt;
			font-size: 7pt;
			font-weight: 700;
		}

		.total-amt {
			text-align: right;
		}

		.total-cur {
			color: #666;
			width: 8%;
		}

		/* ===== FOOTER ===== */
		.tbl-bank {
			width: 100%;
			border-collapse: collapse;
		}

		.tbl-bank td {
			padding: 1.5pt 2pt;
			font-size: 6.5pt;
			font-style: italic;
		}

		.bank-label {
			color: #888;
			white-space: nowrap;
		}

		.bank-sep {
			color: #888;
			width: 6pt;
		}

		.bank-val {
			font-weight: 500;
			padding-left: 4pt;
		}

		.thp-label {
			font-size: 5.5pt;
			font-weight: 700;
			letter-spacing: 2pt;
			color: #888;
			text-transform: uppercase;
			margin-bottom: 3pt;
		}

		.thp-amount {
			font-size: 14pt;
			font-weight: 700;
			letter-spacing: -0.5pt;
			border-bottom: 1.5pt solid #1a1a1a;
			padding-bottom: 2pt;
			margin-bottom: 1pt;
		}

		.thp-rp {
			font-size: 8pt;
			font-weight: 400;
			color: #555;
			margin-right: 3pt;
		}

		/* ===== BENEFIT ===== */
		.tbl-benefit {
			width: 100%;
			border: 1pt solid #1a1a1a;
			border-collapse: collapse;
			margin-top: 6pt;
		}

		.benefit-header td {
			font-size: 5pt;
			font-weight: 700;
			letter-spacing: 2.5pt;
			color: #555;
			text-align: center;
			padding: 4pt 0;
			border-bottom: 1pt solid #1a1a1a;
			background: #f7f7f5;
		}

		.tbl-benefit td {
			padding: 2pt 10pt;
			font-size: 6pt;
		}

		.benefit-note td {
			font-size: 5pt;
			color: #888;
			font-style: italic;
			padding: 4pt 10pt;
			border-top: 1pt solid #eee;
		}

		/* ===== HELPERS ===== */
		.br {
			border-right: 1pt solid #1a1a1a;
		}

		.bb {
			border-bottom: 1pt solid #1a1a1a;
		}

		.tr {
			text-align: right;
		}

		/* ===== DISCLAIMER ===== */
		.disclaimer {
			font-size: 5.5pt;
			font-style: italic;
			font-weight: 600;
			text-align: justify;
			margin-top: 5pt;
			line-height: 1.4;
			color: #555;
		}
	</style>
</head>

<body>

	@php
		$logoPath = public_path('hompimplay_icon.png');
		$logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
	@endphp

	@php
		$itemMap = $payroll->items->keyBy(function ($item) {
		    return $item->component->nama ?? $item->nama_item;
		});
	@endphp

	{{-- ===== WATERMARK OVERLAY ===== --}}
	@if ($logoBase64)
		<div
			style="
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        z-index: 0;
        pointer-events: none;
        background-image: url('{{ $logoBase64 }}');
        background-repeat: repeat;
        background-size: 80px auto;
        background-position: 10px 10px;
        opacity: 0.03;
        /* transform: rotate(-25deg); */
        transform-origin: center;
    ">
		</div>
	@endif

	<div style="position: relative; z-index: 1;">

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
								<span class="absen-label">Libur Nasional</span>
							</td>
							<td>
								<span class="absen-val">{{ $payroll->libur_nasional ?? 0 }} Hari</span>
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

		<div class="disclaimer">
			HARAP DIPERHATIKAN, ISI PERNYATAAN INI ADALAH RAHASIA KECUALI ANDA DIMINTA UNTUK MENGUNGKAPKANNYA UNTUK
			KEPERLUAN PAJAK, HUKUM, ATAU KEPENTINGAN PEMERINTAH. SETIAP PELANGGARAN ATAS KEWAJIBAN MENJAGA KERAHASIAAN
			INI AKAN DIKENAKAN SANKSI, YANG MUNGKIN BERUPA TINDAKAN KEDISIPLINAN.
		</div>

	</div> {{-- end z-index wrapper --}}

</body>

</html>
