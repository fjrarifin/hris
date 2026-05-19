@extends('layouts.app')

@section('title', 'Detail Karyawan')
@section('page-title', 'Detail Karyawan')

@section('content')
	@php
		$posisiOptions = collect($posisiOptions ?? []);
		$divisiOptions = collect($divisiOptions ?? []);
		$departementOptions = collect($departementOptions ?? []);
		$atasanOptions = collect($atasanOptions ?? []);
		$photo = optional($data->user)->photo;
		$initial = strtoupper(substr($data->nama_karyawan ?: 'K', 0, 1));
		$fmtDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d M Y') : '-';
		$currentEmail = old('email', $data->email ?: optional($data->user)->email);
		$currentPosisi = old('posisi', $data->posisi);
		$currentDivisi = old('divisi', $data->divisi);
		$currentDepartement = old('departement', $data->departement);
		$currentAtasan = old('nama_atasan_langsung', $data->nama_atasan_langsung);
		$currentAtasanTidakLangsung = old('atasan_tidak_langsung', $data->atasan_tidak_langsung);
	@endphp

	<style>
		.hr-karyawan-detail {
			font-size: 11px;
		}

		.hr-karyawan-detail h1,
		.hr-karyawan-detail h2,
		.hr-karyawan-detail h3 {
			font-size: 14px !important;
			line-height: 1.35;
		}

		.hr-karyawan-detail h4,
		.hr-karyawan-detail label,
		.hr-karyawan-detail .subheading,
		.hr-karyawan-detail .tab-btn {
			font-size: 12px !important;
			line-height: 1.35;
		}

		.hr-karyawan-detail input,
		.hr-karyawan-detail select,
		.hr-karyawan-detail textarea,
		.hr-karyawan-detail table,
		.hr-karyawan-detail .body-text,
		.hr-karyawan-detail .content-text {
			font-size: 11px !important;
		}

		.profile-avatar {
			aspect-ratio: 1 / 1;
			border-radius: 50%;
			display: block;
			object-fit: cover;
		}

		.profile-avatar-button {
			background: transparent;
			border: 0;
			cursor: pointer;
			padding: 0;
			position: relative;
		}

		.avatar-edit-mark {
			align-items: center;
			background: #2563eb;
			border: 2px solid #fff;
			border-radius: 999px;
			bottom: 3px;
			color: #fff;
			display: inline-flex;
			height: 28px;
			justify-content: center;
			position: absolute;
			right: 3px;
			width: 28px;
		}

		.tab-btn {
			align-items: center;
			background: #f8fafc;
			color: #6b7280;
			border: 0;
			border-right: 1px solid #e5e7eb;
			display: flex;
			justify-content: center;
			min-height: 46px;
			padding: 8px 10px;
			text-align: center;
			transition: 0.2s;
			width: 100%;
		}

		.tab-btn:hover {
			background: #e5e7eb;
		}

		.active-tab {
			background: white !important;
			color: #111827 !important;
			border-bottom: 0 !important;
			box-shadow: inset 0 2px 0 #4f46e5;
		}

		.hr-tabs {
			background: #f8fafc;
			border: 1px solid #e5e7eb;
			border-bottom: 0;
			border-radius: 12px 12px 0 0;
			display: grid;
			grid-template-columns: repeat(3, minmax(0, 1fr));
			overflow: hidden;
			width: 100%;
		}

		.hr-tabs .tab-btn:last-child {
			border-right: 0;
		}

		.tab-panel {
			border-radius: 0 0 12px 12px;
			min-width: 0;
		}

		.table-scroll {
			overflow-x: auto;
			-webkit-overflow-scrolling: touch;
			width: 100%;
		}

		.contract-table {
			min-width: 660px;
			table-layout: fixed;
		}

		.contract-table th,
		.contract-table td {
			vertical-align: middle;
			word-break: normal;
		}

		.contract-table .col-small {
			width: 52px;
		}

		.contract-table .col-action {
			width: 98px;
		}

		.contract-table .col-date {
			width: 105px;
		}

		.contract-table .col-status {
			width: 96px;
		}
	</style>

	<div class="hr-karyawan-detail">
		<div class="row align-items-start">
			<div class="col-md-4">
				<div class="card card-outline card-primary mb-4 rounded-3xl shadow-sm">
					<div class="card-body text-center py-4">
						<div class="d-flex justify-content-center mb-3">
							<button type="button" class="profile-avatar-button" data-toggle="modal" data-target="#photoActionModal">
								@if ($photo)
									<img src="{{ asset('storage/' . $photo) }}" class="profile-avatar shadow" width="132" height="132"
										oncontextmenu="return false;" draggable="false">
								@else
									<div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white shadow"
										style="width:132px;height:132px;font-size:44px;">
										{{ $initial }}
									</div>
								@endif
								<span class="avatar-edit-mark">
									<i class="fas fa-camera"></i>
								</span>
							</button>
						</div>

						<h2 class="font-extrabold mb-1 text-gray-900">
							{{ $data->nama_karyawan }}
						</h2>

						<div class="text-muted mb-2">
							{{ $data->jabatan ?: '-' }} &bull; {{ $data->departement ?: '-' }}
						</div>

						<span class="badge badge-success px-3 py-1">
							{{ $data->status_karyawan ?? 'AKTIF' }}
						</span>

						<div class="small text-muted mt-3">
							Bergabung sejak<br>
							<strong>{{ $fmtDate($data->join_date) }}</strong>
						</div>

						<div class="border-top mt-4 pt-3 text-left">
							<div class="d-flex justify-content-between mb-2">
								<span class="text-muted">NIK</span>
								<strong>{{ $data->nik }}</strong>
							</div>
							<div class="d-flex justify-content-between mb-2">
								<span class="text-muted">No. HP</span>
								<strong>{{ $data->no_hp ?: '-' }}</strong>
							</div>
							<div class="d-flex justify-content-between mb-2">
								<span class="text-muted">Email</span>
								<strong class="d-block text-break">{{ $currentEmail ?: '-' }}</strong>
							</div>
							<div class="d-flex justify-content-between">
								<span class="text-muted">Atasan</span>
								<strong class="text-right">{{ $data->nama_atasan_langsung ?: '-' }}</strong>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="col-md-8">
				<div class="relative">
					<div class="hr-tabs">
						<button onclick="openTab('tab-info')" id="btn-info" class="tab-btn font-semibold"
							type="button">
							Info & Kontak
						</button>

						<button onclick="openTab('tab-pribadi')" id="btn-pribadi" class="tab-btn font-semibold"
							type="button">
							Data Pribadi
						</button>

						<button onclick="openTab('tab-kontrak')" id="btn-kontrak" class="tab-btn font-semibold"
							type="button">
							Kontrak
						</button>
					</div>

					<div class="tab-panel bg-white p-4 shadow-sm">
				<form method="POST" action="{{ route('hr.karyawan.update', $data->nik) }}">
					@csrf

					<div id="tab-info" class="tab-content">
						<div class="mb-3 p-1">
							<h3 class="mb-4 font-extrabold text-gray-900">
								Informasi Karyawan
							</h3>

							<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
								<div>
									<label class="font-bold text-gray-600">Nama Karyawan</label>
									<input type="text" name="nama_karyawan" value="{{ old('nama_karyawan', $data->nama_karyawan) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" required>
								</div>

								<div>
									<label class="font-bold text-gray-600">Jabatan</label>
									<input type="text" name="jabatan" value="{{ old('jabatan', $data->jabatan) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">Posisi</label>
									<select name="posisi" class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
										<option value="">- Pilih -</option>
										@foreach ($posisiOptions as $option)
											<option value="{{ $option }}" @selected((string) $currentPosisi === (string) $option)>
												{{ $option }}
											</option>
										@endforeach
										@if ($currentPosisi && ! $posisiOptions->contains($currentPosisi))
											<option value="{{ $currentPosisi }}" selected>{{ $currentPosisi }}</option>
										@endif
									</select>
								</div>

								<div>
									<label class="font-bold text-gray-600">Divisi</label>
									<select name="divisi" class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
										<option value="">- Pilih -</option>
										@foreach ($divisiOptions as $option)
											<option value="{{ $option }}" @selected((string) $currentDivisi === (string) $option)>
												{{ $option }}
											</option>
										@endforeach
										@if ($currentDivisi && ! $divisiOptions->contains($currentDivisi))
											<option value="{{ $currentDivisi }}" selected>{{ $currentDivisi }}</option>
										@endif
									</select>
								</div>

								<div>
									<label class="font-bold text-gray-600">Departemen</label>
									<select name="departement"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
										<option value="">- Pilih -</option>
										@foreach ($departementOptions as $option)
											<option value="{{ $option }}" @selected((string) $currentDepartement === (string) $option)>
												{{ $option }}
											</option>
										@endforeach
										@if ($currentDepartement && ! $departementOptions->contains($currentDepartement))
											<option value="{{ $currentDepartement }}" selected>{{ $currentDepartement }}</option>
										@endif
									</select>
								</div>

								<div>
									<label class="font-bold text-gray-600">Unit</label>
									<input type="text" name="unit" value="{{ old('unit', $data->unit) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">Atasan Langsung</label>
									<select name="nama_atasan_langsung"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
										<option value="">- Pilih -</option>
										@foreach ($atasanOptions as $atasan)
											<option value="{{ $atasan->nama_karyawan }}" @selected((string) $currentAtasan === (string) $atasan->nama_karyawan)>
												{{ $atasan->nama_karyawan }} - {{ $atasan->nik }}
											</option>
										@endforeach
										@if ($currentAtasan && ! $atasanOptions->pluck('nama_karyawan')->contains($currentAtasan))
											<option value="{{ $currentAtasan }}" selected>{{ $currentAtasan }}</option>
										@endif
									</select>
								</div>

								<div>
									<label class="font-bold text-gray-600">Atasan Tidak Langsung</label>
									<select name="atasan_tidak_langsung"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
										<option value="">- Pilih -</option>
										@foreach ($atasanOptions as $atasan)
											<option value="{{ $atasan->nama_karyawan }}" @selected((string) $currentAtasanTidakLangsung === (string) $atasan->nama_karyawan)>
												{{ $atasan->nama_karyawan }} - {{ $atasan->nik }}
											</option>
										@endforeach
										@if ($currentAtasanTidakLangsung && ! $atasanOptions->pluck('nama_karyawan')->contains($currentAtasanTidakLangsung))
											<option value="{{ $currentAtasanTidakLangsung }}" selected>{{ $currentAtasanTidakLangsung }}</option>
										@endif
									</select>
								</div>

								<div>
									<label class="font-bold text-gray-600">Jenis Kelamin</label>
									<select name="jenis_kelamin"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
										<option value="">- Pilih -</option>
										<option value="L" @selected(old('jenis_kelamin', $data->jenis_kelamin) === 'L')>Laki-laki</option>
										<option value="P" @selected(old('jenis_kelamin', $data->jenis_kelamin) === 'P')>Perempuan</option>
									</select>
								</div>
							</div>

							<h4 class="subheading mb-3 mt-5 font-extrabold text-gray-900">
								Kontak dan Payroll
							</h4>

							<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
								<div>
									<label class="font-bold text-gray-600">No. HP</label>
									<input type="text" name="no_hp" value="{{ old('no_hp', $data->no_hp) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">Email</label>
									<input type="email" name="email" value="{{ $currentEmail }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">Tanggal Bergabung</label>
									<input type="date" name="join_date" value="{{ old('join_date', optional($data->join_date)->format('Y-m-d')) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">No. Rekening</label>
									<input type="text" name="no_rekening" value="{{ old('no_rekening', $data->no_rekening) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">Nama Bank</label>
									<input type="text" name="bank" value="{{ old('bank', $data->bank) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">BPJS</label>
									<select name="bpjs" class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
										<option value="0" @selected((string) old('bpjs', (int) $data->bpjs) === '0')>Tidak Aktif</option>
										<option value="1" @selected((string) old('bpjs', (int) $data->bpjs) === '1')>Aktif</option>
									</select>
								</div>

								<div>
									<label class="font-bold text-gray-600">No. BPJS</label>
									<input type="text" name="no_bpjs" value="{{ old('no_bpjs', $data->no_bpjs) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>
							</div>
						</div>

						<div class="mt-6 border-t pt-4">
							<div class="flex w-full flex-col gap-3 md:flex-row md:items-center md:justify-end">
								<a href="{{ route('hr.karyawan.index') }}"
									class="rounded-lg bg-gray-100 px-5 py-2 text-center font-semibold transition hover:bg-gray-200">
									Batal
								</a>

								<button type="submit"
									class="rounded-lg bg-indigo-600 px-5 py-2 font-semibold text-white transition hover:bg-indigo-700">
									<i class="fas fa-save mr-1"></i>
									Simpan Perubahan
								</button>
							</div>
						</div>
					</div>

					<div id="tab-pribadi" class="tab-content hidden">
						<div class="mb-3 p-1">
							<h3 class="mb-4 font-extrabold text-gray-900">
								Data Pribadi
							</h3>

							<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
								<div>
									<label class="font-bold text-gray-600">Nomor KTP</label>
									<input type="text" name="no_ktp" value="{{ old('no_ktp', $data->no_ktp) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">Tempat Lahir</label>
									<input type="text" name="tempat_lahir" value="{{ old('tempat_lahir', $data->tempat_lahir) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">Tanggal Lahir</label>
									<input type="date" name="tanggal_lahir"
										value="{{ old('tanggal_lahir', optional($data->tanggal_lahir)->format('Y-m-d')) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div class="md:col-span-3">
									<label class="font-bold text-gray-600">Alamat</label>
									<textarea name="alamat" rows="3"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">{{ old('alamat', $data->alamat) }}</textarea>
								</div>

								<div>
									<label class="font-bold text-gray-600">NPWP</label>
									<select name="npwp" class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
										<option value="0" @selected((string) old('npwp', (int) $data->npwp) === '0')>Tidak Ada</option>
										<option value="1" @selected((string) old('npwp', (int) $data->npwp) === '1')>Ada</option>
									</select>
								</div>

								<div>
									<label class="font-bold text-gray-600">Nomor NPWP</label>
									<input type="text" name="no_npwp" value="{{ old('no_npwp', $data->no_npwp) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">Status Pernikahan</label>
									<select name="status_pernikahan"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
										<option value="">- Pilih -</option>
										@foreach (['Belum Menikah', 'Menikah', 'Cerai Hidup', 'Cerai Mati'] as $statusNikah)
											<option value="{{ $statusNikah }}" @selected(old('status_pernikahan', $data->status_pernikahan) === $statusNikah)>
												{{ $statusNikah }}
											</option>
										@endforeach
									</select>
								</div>

								<div>
									<label class="font-bold text-gray-600">Agama</label>
									<select name="agama" class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
										<option value="">- Pilih -</option>
										@foreach (['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'] as $agama)
											<option value="{{ $agama }}" @selected(old('agama', $data->agama) === $agama)>{{ $agama }}</option>
										@endforeach
									</select>
								</div>

								<div>
									<label class="font-bold text-gray-600">Kewarganegaraan</label>
									<input type="text" name="kewarganegaraan" value="{{ old('kewarganegaraan', $data->kewarganegaraan ?: 'Indonesia') }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>
							</div>

							<h4 class="subheading mb-3 mt-5 font-extrabold text-gray-900">
								Pendidikan
							</h4>

							<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
								<div>
									<label class="font-bold text-gray-600">Pendidikan Terakhir</label>
									<select name="pendidikan_terakhir"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
										<option value="">- Pilih -</option>
										@foreach (['SD', 'SMP', 'SMA/SMK', 'D1', 'D2', 'D3', 'D4/S1', 'S2', 'S3'] as $pendidikan)
											<option value="{{ $pendidikan }}" @selected(old('pendidikan_terakhir', $data->pendidikan_terakhir) === $pendidikan)>
												{{ $pendidikan }}
											</option>
										@endforeach
									</select>
								</div>

								<div>
									<label class="font-bold text-gray-600">Nama Institusi</label>
									<input type="text" name="nama_institusi" value="{{ old('nama_institusi', $data->nama_institusi) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">Jurusan</label>
									<input type="text" name="jurusan" value="{{ old('jurusan', $data->jurusan) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>
							</div>

							<h4 class="subheading mb-3 mt-5 font-extrabold text-gray-900">
								Data Keluarga dan Kontak Darurat
							</h4>

							<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
								<div>
									<label class="font-bold text-gray-600">Nama Pasangan</label>
									<input type="text" name="nama_pasangan" value="{{ old('nama_pasangan', $data->nama_pasangan) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">Jumlah Anak</label>
									<input type="number" name="jumlah_anak" min="0" value="{{ old('jumlah_anak', $data->jumlah_anak) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">Nama Ayah</label>
									<input type="text" name="nama_ayah" value="{{ old('nama_ayah', $data->nama_ayah) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">Nama Ibu</label>
									<input type="text" name="nama_ibu" value="{{ old('nama_ibu', $data->nama_ibu) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">Kontak Darurat</label>
									<input type="text" name="kontak_darurat_nama" value="{{ old('kontak_darurat_nama', $data->kontak_darurat_nama) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">Hubungan Kontak Darurat</label>
									<input type="text" name="kontak_darurat_hubungan"
										value="{{ old('kontak_darurat_hubungan', $data->kontak_darurat_hubungan) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>

								<div>
									<label class="font-bold text-gray-600">No. HP Kontak Darurat</label>
									<input type="text" name="kontak_darurat_no_hp"
										value="{{ old('kontak_darurat_no_hp', $data->kontak_darurat_no_hp) }}"
										class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
								</div>
							</div>
						</div>

						<div class="mt-6 border-t pt-4">
							<div class="flex w-full flex-col gap-3 md:flex-row md:items-center md:justify-end">
								<a href="{{ route('hr.karyawan.index') }}"
									class="rounded-lg bg-gray-100 px-5 py-2 text-center font-semibold transition hover:bg-gray-200">
									Batal
								</a>

								<button type="submit"
									class="rounded-lg bg-indigo-600 px-5 py-2 font-semibold text-white transition hover:bg-indigo-700">
									<i class="fas fa-save mr-1"></i>
									Simpan Perubahan
								</button>
							</div>
						</div>
					</div>
				</form>

				<div id="tab-kontrak" class="tab-content hidden">
					<div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
						<h3 class="font-extrabold text-gray-900">
							Kontrak Karyawan
						</h3>

						<button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700"
							data-toggle="modal" data-target="#addKontrakModal">
							<i class="fas fa-plus mr-1"></i>
							Tambah Kontrak
						</button>
					</div>

					<h3 class="mb-3 font-extrabold text-gray-900">
						Kontrak Sedang Berjalan
					</h3>

					<div class="table-scroll mb-5 rounded-lg border">
						<table class="contract-table w-full">
							<thead class="bg-gray-50 text-gray-600">
								<tr>
									<th class="col-small px-3 py-2 text-left">Ke</th>
									<th class="col-status px-3 py-2 text-left">Status</th>
									<th class="col-date px-3 py-2 text-left">Start</th>
									<th class="col-date px-3 py-2 text-left">End</th>
									<th class="px-3 py-2 text-left">Durasi</th>
									<th class="col-action px-3 py-2 text-right">Aksi</th>
								</tr>
							</thead>
							<tbody>
								@forelse ($kontrakBerjalan as $k)
									<tr class="border-t">
										<td class="px-3 py-2">{{ $k->kontrak_ke }}</td>
										<td class="px-3 py-2 font-bold text-green-700">{{ $k->status_kontrak }}</td>
										<td class="px-3 py-2">{{ $fmtDate($k->start_date) }}</td>
										<td class="px-3 py-2">{{ $fmtDate($k->end_date) }}</td>
										<td class="px-3 py-2">{{ $k->durasi_bulan ? $k->durasi_bulan . ' bulan' : '-' }}</td>
										<td class="px-3 py-2 text-right">
											<button type="button" class="rounded-lg bg-gray-100 px-3 py-1 font-semibold text-gray-700 hover:bg-gray-200"
												data-toggle="modal" data-target="#editKontrakModal{{ $k->id }}">
												<i class="fas fa-edit mr-1"></i>
												Edit
											</button>
										</td>
									</tr>
								@empty
									<tr>
										<td colspan="6" class="px-3 py-4 text-center text-gray-500">Belum ada kontrak berjalan.</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>

					<h3 class="mb-3 font-extrabold text-gray-900">
						Kontrak Sudah Habis
					</h3>

					<div class="table-scroll rounded-lg border">
						<table class="contract-table w-full">
							<thead class="bg-gray-50 text-gray-600">
								<tr>
									<th class="col-small px-3 py-2 text-left">Ke</th>
									<th class="col-status px-3 py-2 text-left">Status</th>
									<th class="col-date px-3 py-2 text-left">Start</th>
									<th class="col-date px-3 py-2 text-left">End</th>
									<th class="px-3 py-2 text-left">Catatan</th>
								</tr>
							</thead>
							<tbody>
								@forelse ($kontrakSelesai as $k)
									<tr class="border-t">
										<td class="px-3 py-2">{{ $k->kontrak_ke }}</td>
										<td class="px-3 py-2 font-bold text-gray-700">{{ $k->status_kontrak }}</td>
										<td class="px-3 py-2">{{ $fmtDate($k->start_date) }}</td>
										<td class="px-3 py-2">{{ $fmtDate($k->end_date) }}</td>
										<td class="px-3 py-2">{{ $k->catatan ?: '-' }}</td>
									</tr>
								@empty
									<tr>
										<td colspan="5" class="px-3 py-4 text-center text-gray-500">Belum ada kontrak yang sudah habis.</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		</div>
		</div>

		<div class="modal fade" id="addKontrakModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content rounded-3xl">
					<form method="POST" action="{{ route('hr.karyawan.kontrak.store', $data->nik) }}">
						@csrf

						<div class="modal-header">
							<h5 class="modal-title subheading font-weight-bold">Tambah Kontrak</h5>
							<button type="button" class="close" data-dismiss="modal" aria-label="Close">
								<span aria-hidden="true">&times;</span>
							</button>
						</div>

						<div class="modal-body">
							<div class="form-group">
								<label class="font-bold text-gray-600">Kontrak Ke</label>
								<input type="number" name="kontrak_ke" min="1" value="{{ old('kontrak_ke', $nextKontrakKe) }}"
									class="form-control rounded-lg" required>
							</div>

							<div class="form-row">
								<div class="form-group col-md-6">
									<label class="font-bold text-gray-600">Start Date</label>
									<input type="date" name="start_date" value="{{ old('start_date') }}" class="form-control rounded-lg" required>
								</div>

								<div class="form-group col-md-6">
									<label class="font-bold text-gray-600">End Date</label>
									<input type="date" name="end_date" value="{{ old('end_date') }}" class="form-control rounded-lg" required>
								</div>
							</div>

							<div class="form-row">
								<div class="form-group col-md-6">
									<label class="font-bold text-gray-600">Durasi Bulan</label>
									<input type="number" name="durasi_bulan" min="0" value="{{ old('durasi_bulan') }}" class="form-control rounded-lg">
								</div>

								<div class="form-group col-md-6">
									<label class="font-bold text-gray-600">Status Kontrak</label>
									<select name="status_kontrak" class="form-control rounded-lg" required>
										<option value="AKTIF" @selected(old('status_kontrak', 'AKTIF') === 'AKTIF')>AKTIF</option>
										<option value="SELESAI" @selected(old('status_kontrak') === 'SELESAI')>SELESAI</option>
										<option value="DIPERPANJANG" @selected(old('status_kontrak') === 'DIPERPANJANG')>DIPERPANJANG</option>
									</select>
								</div>
							</div>

							<div class="form-group mb-0">
								<label class="font-bold text-gray-600">Catatan</label>
								<textarea name="catatan" rows="3" class="form-control rounded-lg">{{ old('catatan') }}</textarea>
							</div>
						</div>

						<div class="modal-footer">
							<button type="button" class="btn btn-outline-secondary rounded-pill" data-dismiss="modal">
								Batal
							</button>
							<button type="submit" class="btn btn-primary rounded-pill px-4">
								<i class="fas fa-plus mr-1"></i>
								Tambah Kontrak
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		@foreach ($kontrakBerjalan as $k)
			<div class="modal fade" id="editKontrakModal{{ $k->id }}" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered">
					<div class="modal-content rounded-3xl">
						<form method="POST" action="{{ route('hr.karyawan.kontrak.update', [$data->nik, $k->id]) }}">
							@csrf

							<div class="modal-header">
								<h5 class="modal-title subheading font-weight-bold">
									Edit Kontrak Ke-{{ $k->kontrak_ke }}
								</h5>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>

							<div class="modal-body">
								<div class="form-row">
									<div class="form-group col-md-6">
										<label class="font-bold text-gray-600">Start Date</label>
										<input type="date" name="start_date"
											value="{{ old('start_date', $k->start_date ? \Carbon\Carbon::parse($k->start_date)->format('Y-m-d') : '') }}"
											class="form-control rounded-lg" required>
									</div>

									<div class="form-group col-md-6">
										<label class="font-bold text-gray-600">End Date</label>
										<input type="date" name="end_date"
											value="{{ old('end_date', $k->end_date ? \Carbon\Carbon::parse($k->end_date)->format('Y-m-d') : '') }}"
											class="form-control rounded-lg" required>
									</div>
								</div>

								<div class="form-row">
									<div class="form-group col-md-6">
										<label class="font-bold text-gray-600">Durasi Bulan</label>
										<input type="number" name="durasi_bulan" min="0" value="{{ old('durasi_bulan', $k->durasi_bulan) }}"
											class="form-control rounded-lg">
									</div>

									<div class="form-group col-md-6">
										<label class="font-bold text-gray-600">Status Kontrak</label>
										<select name="status_kontrak" class="form-control rounded-lg" required>
											@foreach (['AKTIF', 'SELESAI', 'DIPERPANJANG'] as $statusKontrak)
												<option value="{{ $statusKontrak }}" @selected(old('status_kontrak', $k->status_kontrak) === $statusKontrak)>
													{{ $statusKontrak }}
												</option>
											@endforeach
										</select>
									</div>
								</div>

								<div class="form-group mb-0">
									<label class="font-bold text-gray-600">Catatan</label>
									<textarea name="catatan" rows="3" class="form-control rounded-lg">{{ old('catatan', $k->catatan) }}</textarea>
								</div>
							</div>

							<div class="modal-footer">
								<button type="button" class="btn btn-outline-secondary rounded-pill" data-dismiss="modal">
									Batal
								</button>
								<button type="submit" class="btn btn-primary rounded-pill px-4">
									<i class="fas fa-save mr-1"></i>
									Simpan Kontrak
								</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		@endforeach

		<div class="modal fade" id="photoActionModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content rounded-3xl">
					<div class="modal-header">
						<h5 class="modal-title subheading font-weight-bold">Foto Profil</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>

					<div class="modal-body">
						<div class="d-flex justify-content-center mb-4">
							@if ($photo)
								<img src="{{ asset('storage/' . $photo) }}" class="profile-avatar shadow" width="132" height="132">
							@else
								<div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white shadow"
									style="width:132px;height:132px;font-size:44px;">
									{{ $initial }}
								</div>
							@endif
						</div>

						<div class="d-flex flex-column">
							@if ($photo)
								<a href="{{ asset('storage/' . $photo) }}" target="_blank" rel="noopener"
									class="btn btn-outline-primary rounded-pill mb-2">
									<i class="fas fa-external-link-alt mr-1"></i>
									Lihat Foto
								</a>
							@endif

							<button type="button" class="btn btn-primary rounded-pill" data-dismiss="modal" data-toggle="modal"
								data-target="#updatePhotoModal">
								<i class="fas fa-camera mr-1"></i>
								Perbarui Foto Profil
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="modal fade" id="updatePhotoModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content rounded-3xl">
					<form method="POST" action="{{ route('hr.karyawan.photo.update', $data->nik) }}" enctype="multipart/form-data">
						@csrf

						<div class="modal-header">
							<h5 class="modal-title subheading font-weight-bold">Perbarui Foto Profil</h5>
							<button type="button" class="close" data-dismiss="modal" aria-label="Close">
								<span aria-hidden="true">&times;</span>
							</button>
						</div>

						<div class="modal-body">
							<div class="form-group mb-0">
								<label class="text-muted">Foto Profil</label>

								<div id="hrPhotoDropzone" class="border rounded-xl p-4 text-center bg-light"
									style="cursor:pointer; border-style:dashed !important;">
									<i class="fas fa-cloud-upload-alt fa-2x text-primary mb-2"></i>
									<div class="font-weight-bold">Pilih Foto</div>
									<div id="hrPhotoFileName" class="small text-muted mt-2"></div>

									<input type="file" id="hrPhotoInput" name="photo" class="d-none" accept="image/*" required>
								</div>

								<button type="button" id="hrPreviewPhotoBtn" class="btn btn-outline-primary btn-sm rounded-pill mt-3 d-none">
									<i class="fas fa-eye mr-1"></i>
									Pratinjau Foto
								</button>

								<div id="hrPhotoPreviewWrapper" class="mt-3 d-none text-center">
									<img id="hrPhotoPreview" src="" class="profile-avatar mx-auto shadow" width="120" height="120">
								</div>
							</div>
						</div>

						<div class="modal-footer">
							<button type="button" class="btn btn-outline-secondary rounded-pill" data-dismiss="modal">
								Batal
							</button>
							<button class="btn btn-primary rounded-pill px-4">
								<i class="fas fa-save mr-1"></i>
								Simpan Foto
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('scripts')
	<script>
		function openTab(tabId) {
			document.querySelectorAll('.tab-content').forEach(el => {
				el.classList.add('hidden');
			});

			document.querySelectorAll('.tab-btn').forEach(el => {
				el.classList.remove('active-tab');
				el.classList.add('bg-gray-100', 'text-gray-500');
			});

			document.getElementById(tabId).classList.remove('hidden');

			const btn = document.getElementById('btn-' + tabId.replace('tab-', ''));
			btn.classList.add('active-tab');
			btn.classList.remove('bg-gray-100', 'text-gray-500');
		}

		document.addEventListener('DOMContentLoaded', function () {
			openTab('tab-info');

			const dropzone = document.getElementById('hrPhotoDropzone');
			const input = document.getElementById('hrPhotoInput');
			const fileName = document.getElementById('hrPhotoFileName');
			const previewBtn = document.getElementById('hrPreviewPhotoBtn');
			const previewWrapper = document.getElementById('hrPhotoPreviewWrapper');
			const preview = document.getElementById('hrPhotoPreview');

			if (!dropzone || !input) return;

			let selectedFile = null;

			dropzone.addEventListener('click', function () {
				input.click();
			});

			dropzone.addEventListener('dragover', function (e) {
				e.preventDefault();
				dropzone.classList.add('border-primary');
			});

			dropzone.addEventListener('dragleave', function () {
				dropzone.classList.remove('border-primary');
			});

			dropzone.addEventListener('drop', function (e) {
				e.preventDefault();
				dropzone.classList.remove('border-primary');

				if (e.dataTransfer.files.length > 0) {
					handleSelectedFile(e.dataTransfer.files[0]);
				}
			});

			input.addEventListener('change', function () {
				if (input.files.length > 0) {
					handleSelectedFile(input.files[0]);
				}
			});

			previewBtn.addEventListener('click', function () {
				if (!selectedFile) return;

				const reader = new FileReader();
				reader.onload = function (e) {
					preview.src = e.target.result;
					previewWrapper.classList.remove('d-none');
				};
				reader.readAsDataURL(selectedFile);
			});

			async function handleSelectedFile(file) {
				if (!file.type.startsWith('image/')) {
					alert('File harus berupa gambar.');
					input.value = '';
					selectedFile = null;
					fileName.textContent = '';
					previewBtn.classList.add('d-none');
					previewWrapper.classList.add('d-none');
					return;
				}

				try {
					selectedFile = await makeSquareImage(file);
				} catch (error) {
					alert('Foto tidak bisa diproses. Silakan pilih file gambar lain.');
					input.value = '';
					selectedFile = null;
					fileName.textContent = '';
					previewBtn.classList.add('d-none');
					previewWrapper.classList.add('d-none');
					return;
				}

				const transfer = new DataTransfer();
				transfer.items.add(selectedFile);
				input.files = transfer.files;

				fileName.textContent = 'File dipilih: ' + file.name;
				previewBtn.classList.remove('d-none');
				previewWrapper.classList.add('d-none');
				preview.src = '';
			}

			function makeSquareImage(file) {
				return new Promise(function (resolve, reject) {
					const image = new Image();
					const url = URL.createObjectURL(file);

					image.onload = function () {
						const side = Math.min(image.naturalWidth, image.naturalHeight);
						const sourceX = (image.naturalWidth - side) / 2;
						const sourceY = (image.naturalHeight - side) / 2;
						const canvasSize = 512;
						const canvas = document.createElement('canvas');
						const context = canvas.getContext('2d');

						canvas.width = canvasSize;
						canvas.height = canvasSize;
						context.drawImage(image, sourceX, sourceY, side, side, 0, 0, canvasSize, canvasSize);

						canvas.toBlob(function (blob) {
							URL.revokeObjectURL(url);

							if (!blob) {
								reject(new Error('Canvas conversion failed'));
								return;
							}

							const extension = file.type === 'image/png' ? 'png' : 'jpg';
							const fileNameWithoutExtension = file.name.replace(/\.[^.]+$/, '') || 'profile-photo';

							resolve(new File(
								[blob],
								fileNameWithoutExtension + '-square.' + extension,
								{ type: blob.type, lastModified: Date.now() }
							));
						}, file.type === 'image/png' ? 'image/png' : 'image/jpeg', 0.9);
					};

					image.onerror = function () {
						URL.revokeObjectURL(url);
						reject(new Error('Image load failed'));
					};

					image.src = url;
				});
			}
		});
	</script>
@endpush
