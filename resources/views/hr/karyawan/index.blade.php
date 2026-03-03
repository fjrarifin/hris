@extends('layouts.app')

@section('title', 'Master Karyawan')
@section('page-title', 'Master Karyawan')

@section('content')
	<style>
		.avatar-sm {
			width: 32px;
			height: 32px;
			border-radius: 50%;
			object-fit: cover;
			cursor: pointer;
			transition: all 0.2s ease;
		}

		.avatar-sm:hover {
			transform: scale(1.08);
			box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
		}

		/* Floating preview */
		.avatar-preview {
			position: fixed;
			width: 140px;
			height: 140px;
			border: 4px solid white;
			backdrop-filter: blur(3px);
			border-radius: 50%;
			object-fit: cover;
			box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
			z-index: 9999;
			pointer-events: none;
			transform: scale(0);
			transition: all 0.15s ease;
		}

		.avatar-preview.show {
			transform: scale(1);
		}

		.table-hover tr:hover {
			background: linear-gradient(90deg, #f8fafc, #eef2ff);
			transition: 0.2s ease;
		}

		#tblKaryawan {
			border-collapse: separate;
			border-spacing: 0 8px;
		}

		#tblKaryawan thead th {
			background: #f1f5f9;
			font-weight: 600;
			border-bottom: 2px solid #e2e8f0;
		}

		#tblKaryawan tbody tr {
			border-bottom: 1px solid #f1f5f9;
			background: white;
			border-radius: 12px;
			transition: all 0.2s ease;
		}

		#tblKaryawan tbody tr:hover {
			transform: translateY(-2px);
			box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
		}

		#tblKaryawan tbody td {
			vertical-align: middle !important;
		}

		#tblKaryawan .btn-detail {
			transition: all 0.2s ease;
		}

		#tblKaryawan .btn-detail:hover {
			transform: translateY(-2px);
			box-shadow: 0 6px 14px rgba(59, 130, 246, 0.25);
		}

		.status-dot {
			position: absolute;
			bottom: 0;
			right: 0;
			width: 8px;
			height: 8px;
			background: #22c55e;
			border-radius: 50%;
			border: 2px solid white;
		}
	</style>

	{{-- Table --}}
	<div class="card-outline card-primary overflow-hidden rounded-3xl bg-white shadow-sm">
		<div class="p-4">
			<table id="tblKaryawan" class="table-bordered table-striped table-hover table w-full text-xs">
				<thead class="bg-gray-50 text-gray-600">
					<tr>
						<th class="px-4 py-2 text-center font-bold">NIK</th>
						<th class="px-4 py-2 text-center font-bold">Nama</th>
						<th class="px-4 py-2 text-center font-bold">Jabatan</th>
						<th class="px-4 py-2 text-center font-bold">Posisi</th>
						<th class="px-4 py-2 text-center font-bold">Aksi</th>
					</tr>
				</thead>

				<tbody>
					@forelse ($karyawan as $r)
						<tr>
							<td class="text-muted px-2 py-2 text-xs font-medium text-gray-700">
								{{ $r->nik }}
							</td>

							<td class="px-2 py-2">
								<div class="d-flex align-items-center position-relative avatar-wrapper">

									{{-- FOTO --}}
									@if (optional($r->user)->photo)
										<div class="position-relative mr-2">
											<img class="avatar-sm" src="{{ asset('storage/' . $r->user->photo) }}"
												data-avatar="{{ asset('storage/' . $r->user->photo) }}" oncontextmenu="return false;" draggable="false"><span
												class="status-dot"></span>
										</div>
									@endif

									{{-- NAMA --}}
									<div>
										<div class="font-bold text-gray-900">
											{{ $r->nama_karyawan }}
										</div>
									</div>

								</div>
							</td>

							<td class="px-2 py-2 text-xs text-gray-600">
								{{ $r->jabatan ?? '-' }}
							</td>

							<td class="px-2 py-2 text-xs text-gray-600">
								{{ $r->posisi ?? '-' }}
							</td>

							<td class="px-3 py-2 text-center">
								<a href="{{ route('hr.karyawan.detail', $r->nik) }}"
									class="rounded-xl bg-blue-500 px-4 py-1.5 text-xs font-bold text-white hover:bg-blue-600">
									Detail
								</a>
							</td>

						</tr>
					@empty
						<tr>
							<td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500">
								Data monitoring belum tersedia.
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
		let preview = document.createElement('img');
		preview.classList.add('avatar-preview');
		document.body.appendChild(preview);

		document.addEventListener('mouseover', function(e) {
			if (e.target.classList.contains('avatar-sm') && e.target.dataset.avatar) {
				preview.src = e.target.dataset.avatar;
				preview.classList.add('show');
			}
		});

		document.addEventListener('mousemove', function(e) {
			if (preview.classList.contains('show')) {
				preview.style.top = (e.clientY - 70) + 'px';
				preview.style.left = (e.clientX + 20) + 'px';
			}
		});

		document.addEventListener('mouseout', function(e) {
			if (e.target.classList.contains('avatar-sm')) {
				preview.classList.remove('show');
			}
		});
	</script>
	<script>
		$(document).ready(function() {
			$('#tblKaryawan').DataTable({
				responsive: true,
				autoWidth: false,
				pageLength: 10,
				sort: false,
				order: [
					[1, 'asc']
				], // sort by Nama

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

				dom: '<"row"<"col-md-6"B><"col-md-6"f>>' +
					'<"row"<"col-12"tr>>' +
					'<"row mt-2"<"col-md-5"i><"col-md-7"p>>',

				buttons: [{
						extend: 'excel',
						text: '<i class="fas fa-file-excel"></i> Excel',
						className: 'btn btn-sm btn-success',
						title: 'Data Master Karyawan',
						exportOptions: {
							columns: [0, 1, 2, 3] // 🔥 hanya kolom valid
						}
					},
					{
						extend: 'pdf',
						text: '<i class="fas fa-file-pdf"></i> PDF',
						className: 'btn btn-sm btn-danger',
						title: 'Data Master Karyawan',
						orientation: 'landscape',
						pageSize: 'A4',
						exportOptions: {
							columns: [0, 1, 2, 3]
						}
					}
				],

				columnDefs: [{
					targets: 4, // kolom Aksi
					orderable: false,
					searchable: false,
					className: 'text-center'
				}]
			});
		});
	</script>
@endpush
