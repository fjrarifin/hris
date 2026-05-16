@extends('layouts.app')

@section('title', 'Data Payroll')
@section('page-title', 'Data Payroll')

@section('content')
	<style>
		.table-hover tr:hover {
			background: linear-gradient(90deg, #f8fafc, #eef2ff);
			transition: 0.2s ease;
		}

		#tblPayroll {
			border-collapse: separate;
			border-spacing: 0 8px;
		}

		#tblPayroll thead th {
			background: #f1f5f9;
			font-weight: 600;
			border-bottom: 2px solid #e2e8f0;
		}

		#tblPayroll tbody tr {
			border-bottom: 1px solid #f1f5f9;
			background: white;
			border-radius: 12px;
			transition: all 0.2s ease;
		}

		#tblPayroll tbody tr:hover {
			transform: translateY(-2px);
			box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
		}

		#tblPayroll tbody td {
			vertical-align: middle !important;
		}

		.btn-action {
			transition: all 0.2s ease;
		}

		.btn-action:hover {
			transform: translateY(-2px);
		}
	</style>

	{{-- Table --}}
	<div class="card-outline card-primary overflow-hidden rounded-3xl bg-white shadow-sm">
		<div class="p-4">

			{{-- Header row: judul + tombol upload --}}
			<div class="mb-3 flex items-center justify-between">
				<h2 class="text-sm font-semibold text-gray-700">Daftar Payroll Karyawan</h2>

				<div class="flex gap-2">
					{{-- 🔄 Sync Karyawan dari GSheet --}}
					{{-- <button onclick="convertPayroll()"
						class="btn-action inline-flex items-center gap-1.5 rounded-xl bg-green-500 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-green-600">
						<i class="fa-solid fa-arrows-rotate"></i>
						Convert Payroll
					</button> --}}

					{{-- 🔄 Sync Karyawan dari GSheet --}}
					<button onclick="syncKaryawan()"
						class="btn-action inline-flex items-center gap-1.5 rounded-xl bg-green-500 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-green-600">
						<i class="fa-solid fa-arrows-rotate"></i>
						Sync Payroll
					</button>

					{{-- Download Template --}}
					{{-- <a href="{{ route('hr.payroll.template') }}"
						class="btn-action inline-flex items-center gap-1.5 rounded-xl bg-gray-500 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-gray-600">
						<i class="fa-solid fa-file-arrow-down"></i>
						Unduh Template
					</a> --}}

					{{-- Upload --}}
					{{-- <a href="{{ route('hr.payroll.upload.form') }}"
						class="btn-action inline-flex items-center gap-1.5 rounded-xl bg-indigo-500 px-4 py-2 text-xs font-bold text-black shadow-sm hover:bg-indigo-600">
						{{-- <i class="fa-solid fa-upload"></i>
						Unggah Payroll
					</a> --}}

					{{-- 🔥 Blast Email --}}
					<button onclick="blastEmail()"
						class="btn-action inline-flex items-center gap-1.5 rounded-xl bg-red-500 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-red-600">
						<i class="fa-solid fa-paper-plane"></i>
						Kirim Email Massal
					</button>
				</div>
			</div>

			<table id="tblPayroll" class="table-bordered table-striped table-hover table w-full text-xs">
				<thead class="bg-gray-50 text-gray-600">
					<tr>
						<th class="px-4 py-2 text-center font-bold">Periode</th>
						<th class="px-4 py-2 text-center font-bold">Nama Karyawan</th>
						<th class="px-4 py-2 text-center font-bold">Departemen</th>
						<th class="px-4 py-2 text-center font-bold">Unit</th>
						<th class="px-4 py-2 text-center font-bold">Jabatan</th>
						<th class="px-4 py-2 text-center font-bold">Total Dibayarkan</th>
						<th class="px-4 py-2 text-center font-bold">Aksi</th>
					</tr>
				</thead>

				<tbody>
					@forelse ($payrolls as $r)
						@php
							$s = \Carbon\Carbon::parse($r->periode_start);
							$e = \Carbon\Carbon::parse($r->periode_end);
						@endphp

						<tr>
							<td class="text-muted px-2 py-2 text-center text-xs font-medium text-gray-700">
								@if ($s->format('Y-m') === $e->format('Y-m'))
									{{ $s->format('d') . ' - ' . $e->format('d M Y') }}
								@else
									{{ $s->format('d M Y') . ' - ' . $e->format('d M Y') }}
								@endif
							</td>

							<td class="px-2 py-2">
								<div class="font-bold text-gray-900">
									{{ $r->karyawan?->nama_karyawan ?? '-' }}
								</div>
								<div class="text-xs text-gray-500">
									NIK: {{ $r->karyawan?->nik ?? '-' }}
								</div>
							</td>

							<td class="px-2 py-2 text-center text-xs text-gray-600">
								{{ $r->karyawan?->departement ?? '-' }}
							</td>

							<td class="px-2 py-2 text-center text-xs text-gray-600">
								{{ $r->karyawan?->unit ?? '-' }}
							</td>

							<td class="px-2 py-2 text-center text-xs text-gray-600">
								{{ $r->karyawan?->jabatan ?? '-' }}
							</td>

							<td class="px-2 py-2 text-right text-xs font-bold text-gray-900" style="white-space: nowrap;">
								Rp {{ number_format($r->total_dibayarkan, 0, ',', '.') }}
							</td>

							<td class="px-3 py-2 text-center" style="white-space: nowrap;">
								{{-- Preview --}}
								<a href="{{ route('hr.payroll.show', $r->id) }}"
									class="btn-action inline-block rounded-xl bg-blue-500 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-blue-600"
									title="Pratinjau Slip Gaji">
									<i class="fas fa-eye"></i>
								</a>

								{{-- Download --}}
								<a href="{{ route('hr.payroll.download', $r->id) }}"
									class="btn-action ml-1 inline-block rounded-xl bg-green-500 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-green-600"
									title="Unduh Slip Gaji">
									<i class="fas fa-download"></i>
								</a>

								{{-- Kirim Email --}}
								<button onclick="kirimEmail({{ $r->id }}, '{{ $r->karyawan?->nama_karyawan }}')"
									class="btn-action ml-1 inline-block rounded-xl bg-amber-500 px-3 py-1.5 text-xs font-bold text-black shadow-sm hover:bg-amber-600"
									title="Kirim Email Slip Gaji">
									<i class="fa-solid fa-envelope"></i>
								</button>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500">
								Data payroll belum tersedia. <a href="{{ route('hr.payroll.upload.form') }}"
									class="text-blue-500 underline hover:text-blue-700">Unggah Payroll Baru</a>
							</td>
						</tr>
					@endforelse
				</tbody>

			</table>
		</div>
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

				language: {
					search: "Cari:",
					lengthMenu: "Tampilkan _MENU_ data",
					zeroRecords: "Data tidak ditemukan",
					info: "Menampilkan _PAGE_ dari _PAGES_",
					infoEmpty: "Tidak ada data",
					infoFiltered: "(difilter dari _MAX_ data)",
					paginate: {
						first: "Pertama",
						last: "Terakhir",
						next: "Selanjutnya",
						previous: "Sebelumnya"
					}
				},

				dom: '<"row"<"col-md-6"l><"col-md-6"f>>' +
					'<"row"<"col-12"tr>>' +
					'<"row mt-2"<"col-md-5"i><"col-md-7"p>>',

				columnDefs: [{
					targets: 6,
					orderable: false,
					searchable: false,
					className: 'text-center'
				}]
			});
		});

		function syncKaryawan() {

			Swal.fire({
				title: 'Sync Data Karyawan?',
				html: 'Data karyawan akan diambil dari <b>Google Sheets</b>.',
				icon: 'question',
				showCancelButton: true,
				confirmButtonColor: '#22c55e',
				cancelButtonColor: '#6b7280',
				confirmButtonText: '<i class="fas fa-rotate mr-1"></i> Sync',
				cancelButtonText: 'Batal',
			}).then((result) => {

				if (!result.isConfirmed) return;

				Swal.fire({
					title: 'Memproses...',
					text: 'Sedang mengambil data dari Google Sheets.',
					allowOutsideClick: false,
					didOpen: () => Swal.showLoading()
				});

				fetch("{{ route('hr.payroll.sync') }}", {
						method: "POST",
						headers: {
							"X-CSRF-TOKEN": "{{ csrf_token() }}",
							"Accept": "application/json"
						}
					})
					.then(res => res.json())
					.then(res => {
						if (res.status) {
							// Pastikan res.data ada, jika tidak set default ke 0
							const inserted = res.data?.inserted || 0;
							const updated  = res.data?.updated  || 0;
							const skipped  = res.data?.skipped  || 0;

							let info = `
								✔️ Data baru: ${inserted} <br>
								🔄 Update: ${updated} <br>
								⏭️ Dilewati: ${skipped}
							`;

							Swal.fire({
								icon: 'success',
								title: 'Sync Selesai',
								html: info
							}).then(() => {
								location.reload();
							});
						} else {
							Swal.fire({
								icon: 'error',
								title: 'Gagal',
								text: res.error
							});
						}
					})
					.catch(err => {
						Swal.fire({
							icon: 'error',
							title: 'Error',
							text: 'Terjadi kesalahan: ' + err
						});
					});

			});
		}

		function convertPayroll() {

			Swal.fire({
				title: 'Convert Data?',
				text: 'Data raw akan diproses ke payroll',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: 'Ya, lanjut'
			}).then((result) => {

				if (!result.isConfirmed) return;

				Swal.fire({
					title: 'Processing...',
					allowOutsideClick: false,
					didOpen: () => Swal.showLoading()
				});

				fetch('/hr/payroll/convert', {
						method: 'POST',
						headers: {
							'X-CSRF-TOKEN': '{{ csrf_token() }}'
						}
					})
					.then(res => res.json())
					.then(res => {
						Swal.fire({
							icon: 'success',
							title: 'Selesai',
							html: `
							✔️ Insert: ${res.data.inserted}<br>
							⏭️ Skip: ${res.data.skipped}
						`
						});
					})
					.catch(err => {
						Swal.fire('Error', err, 'error');
					});
			});
		}

		function kirimEmail(id, nama) {
			Swal.fire({
				title: 'Kirim Slip Gaji?',
				html: `Slip gaji <b>${nama}</b> akan dikirim ke email karyawan.`,
				icon: 'question',
				showCancelButton: true,
				confirmButtonColor: '#f59e0b',
				cancelButtonColor: '#6b7280',
				confirmButtonText: '<i class="fas fa-paper-plane mr-1"></i> Kirim',
				cancelButtonText: 'Batal',
			}).then((result) => {
				if (!result.isConfirmed) return;

				Swal.fire({
					title: 'Mengirim...',
					text: 'Mohon tunggu sebentar.',
					allowOutsideClick: false,
					didOpen: () => Swal.showLoading()
				});

				$.ajax({
					url: `/hr/payroll/${id}/send-email`,
					method: 'POST',
					data: {
						_token: '{{ csrf_token() }}'
					},
					success: function(res) {
						if (res.status) {
							Swal.fire({
								icon: 'success',
								title: 'Berhasil!',
								text: res.message,
								timer: 2500,
								showConfirmButton: false
							});
						} else {
							Swal.fire({
								icon: 'error',
								title: 'Gagal',
								text: res.error ?? 'Terjadi kesalahan.'
							});
						}
					},
					error: function(xhr) {
						Swal.fire({
							icon: 'error',
							title: 'Error',
							text: xhr.responseJSON?.error ?? 'Gagal mengirim email.'
						});
					}
				});
			});
		}

		function blastEmail() {
			Swal.fire({
				title: 'Kirim Email Massal Slip Gaji?',
				html: 'Semua karyawan akan dikirim slip gaji <b>periode terakhir</b>.',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#ef4444',
				cancelButtonColor: '#6b7280',
				confirmButtonText: 'Ya, Kirim!',
				cancelButtonText: 'Batal',
			}).then((result) => {
				if (!result.isConfirmed) return;

				Swal.fire({
					title: 'Mengirim...',
					text: 'Proses sedang berjalan, mohon tunggu.',
					allowOutsideClick: false,
					didOpen: () => Swal.showLoading()
				});

				$.ajax({
					url: `/hr/payroll/blast-email`,
					type: 'POST', // 🔥 pakai ini, lebih pasti
					headers: {
						'X-CSRF-TOKEN': '{{ csrf_token() }}'
					},
					success: function(res) {
						console.log(res);
						if (res.status) {
							Swal.fire({
								icon: 'success',
								title: 'Selesai!',
								text: res.message,
							});
						} else {
							Swal.fire({
								icon: 'error',
								title: 'Gagal',
								text: res.error
							});
						}
					},
					error: function(xhr) {
						console.log(err);
						Swal.fire({
							icon: 'error',
							title: 'Error',
							text: xhr.responseJSON?.error ?? 'Terjadi kesalahan'
						});
					}
				});
			});
		}
	</script>
@endpush
