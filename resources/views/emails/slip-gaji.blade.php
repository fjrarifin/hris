<!DOCTYPE html>
<html lang="id">

<head>
	<meta charset="UTF-8">
	<style>
		body {
			font-family: Arial, sans-serif;
			font-size: 14px;
			color: #333;
			background: #f4f4f4;
			margin: 0;
			padding: 0;
		}

		.wrapper {
			max-width: 600px;
			margin: 30px auto;
			background: #fff;
			border-radius: 12px;
			overflow: hidden;
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
		}

		.header {
			background: #4f46e5;
			color: white;
			padding: 24px 30px;
			text-align: center;
		}

		.header h1 {
			margin: 0;
			font-size: 22px;
			font-weight: bold;
		}

		.header p {
			margin: 4px 0 0;
			font-size: 13px;
			opacity: 0.85;
		}

		.body {
			padding: 28px 30px;
		}

		.body p {
			margin: 0 0 14px;
			line-height: 1.6;
		}

		.info-box {
			background: #f8fafc;
			border: 1px solid #e2e8f0;
			border-radius: 8px;
			padding: 16px 20px;
			margin: 20px 0;
		}

		.info-box table {
			width: 100%;
			border-collapse: collapse;
			font-size: 13px;
		}

		.info-box td {
			padding: 5px 4px;
			vertical-align: top;
		}

		.info-box .label {
			color: #6b7280;
			width: 38%;
		}

		.info-box .value {
			font-weight: bold;
			color: #111827;
		}

		.total-box {
			background: #eff6ff;
			border: 1px solid #bfdbfe;
			border-radius: 8px;
			padding: 14px 20px;
			margin: 20px 0;
			text-align: center;
		}

		.total-box .label {
			font-size: 12px;
			color: #3b82f6;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}

		.total-box .amount {
			font-size: 22px;
			font-weight: bold;
			color: #1d4ed8;
			margin-top: 4px;
		}

		/* Password box — menonjol */
		.password-box {
			background: #fefce8;
			border: 1.5px dashed #f59e0b;
			border-radius: 8px;
			padding: 14px 20px;
			margin: 20px 0;
			text-align: center;
		}

		.password-box .pw-label {
			font-size: 12px;
			color: #92400e;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			margin-bottom: 6px;
		}

		.password-box .pw-value {
			font-size: 26px;
			font-weight: bold;
			color: #b45309;
			letter-spacing: 6px;
			font-family: 'Courier New', monospace;
		}

		.password-box .pw-hint {
			font-size: 11px;
			color: #78350f;
			margin-top: 6px;
		}

		.note {
			font-size: 12px;
			color: #6b7280;
			background: #fff7ed;
			border-left: 3px solid #f97316;
			padding: 10px 14px;
			border-radius: 4px;
			margin-top: 20px;
		}

		.footer {
			background: #f8fafc;
			border-top: 1px solid #e5e7eb;
			padding: 16px 30px;
			text-align: center;
			font-size: 11px;
			color: #9ca3af;
		}
	</style>
</head>

<body>
	<div class="wrapper">

		<div class="header">
			<h1>CV. 3 DETIK</h1>
			<p>Slip Gaji Karyawan</p>
		</div>

		<div class="body">
			<p>Yth. <b>{{ $payroll->karyawan->nama_karyawan }}</b>,</p>
			<p>
				Berikut ini kami sampaikan slip gaji Anda untuk periode
				<b>{{ \Carbon\Carbon::parse($payroll->periode_start)->translatedFormat('d F') }}
					sd {{ \Carbon\Carbon::parse($payroll->periode_end)->translatedFormat('d F Y') }}</b>.
			</p>

			<div class="info-box">
				<table>
					<tr>
						<td class="label">NIK</td>
						<td class="value">{{ $payroll->karyawan->nik }}</td>
					</tr>
					<tr>
						<td class="label">Nama</td>
						<td class="value">{{ $payroll->karyawan->nama_karyawan }}</td>
					</tr>
					<tr>
						<td class="label">Jabatan</td>
						<td class="value">{{ $payroll->karyawan->jabatan ?? '-' }}</td>
					</tr>
					<tr>
						<td class="label">Departemen</td>
						<td class="value">{{ $payroll->karyawan->departement ?? '-' }}</td>
					</tr>
					<tr>
						<td class="label">Periode</td>
						<td class="value">
							{{ \Carbon\Carbon::parse($payroll->periode_start)->translatedFormat('d F') }}
							sd {{ \Carbon\Carbon::parse($payroll->periode_end)->translatedFormat('d F Y') }}
						</td>
					</tr>
				</table>
			</div>

			{{-- PASSWORD BOX --}}
			<div class="password-box">
				<div class="pw-label">🔒 Keamanan File PDF</div>
				<div class="pw-value">Format: DDMMYY</div>
				<div class="pw-hint">
					Gunakan <strong>tanggal lahir</strong> Anda sebagai password untuk membuka file PDF terlampir.<br>
					Contoh: 20 November 1995 menjadi <strong>201195</strong>.
				</div>
			</div>

			<div class="note">
				⚠️ Email ini bersifat rahasia. Harap tidak meneruskan kepada pihak lain.
				Password bersifat pribadi dan hanya untuk Anda.
			</div>

			<p style="margin-top: 20px;">Salam,<br><b>Tim HR — CV. 3 DETIK</b></p>
		</div>

		<div class="footer">
			&copy; {{ date('Y') }} CV. 3 DETIK. Email ini dikirim otomatis, harap tidak membalas.
		</div>

	</div>
</body>

</html>
