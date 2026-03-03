@extends('layouts.app')

@section('title', 'Relasi Master')
@section('page_title', 'Relasi Master')
@section('page_desc', 'Mapping siapa menilai siapa (Master Relasi 360)')

@section('content')

	{{-- HEADER + SEARCH --}}
	<div class="card-outline card-primary mb-4 rounded-3xl bg-white p-6 shadow-sm">
		<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
			<div>
				<h2 class="text-lg font-extrabold text-gray-900">
					Relasi Penilaian (360)
				</h2>
				<p class="text-sm text-gray-500">
					Daftar karyawan dan jumlah penilai pada master relasi
				</p>
			</div>

			{{-- <form method="GET" class="flex gap-2">
				<input type="text" name="q" value="{{ $q }}" placeholder="Cari NIK / Nama / Jabatan..."
					class="w-72 rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
				<button class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white hover:bg-indigo-700">
					Cari
				</button>
			</form> --}}
		</div>
	</div>

	{{-- TABLE --}}
	<div class="card-outline card-primary overflow-hidden rounded-3xl bg-white shadow-sm">
		<div class="p-4">
			<table id="tblRelasi" class="table-bordered table-striped table-hover table w-full text-xs">
				<thead class="bg-gray-50 text-gray-600">
					<tr>
						<th class="text-center">NIK</th>
						<th>Nama</th>
						<th>Jabatan</th>
						<th class="text-center">Total Penilai</th>
						<th class="text-center">Aksi</th>
					</tr>
				</thead>


				<tbody>
					@forelse ($list as $r)
						<tr>
							<td class="px-3 py-2 text-center font-semibold text-gray-700">
								{{ $r->nik }}
							</td>

							<td class="px-3 py-2 font-bold text-gray-900">
								{{ $r->nama_karyawan }}
							</td>

							<td class="px-3 py-2 text-gray-600">
								{{ $r->jabatan }}
							</td>

							<td class="px-3 py-2 text-center">
								<span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold text-indigo-700">
									{{ $r->total_penilai }}
								</span>
							</td>

							<td class="px-3 py-2 text-center">
								<a href="{{ route('hr.360.relasi.detail', $r->nik) }}"
									class="rounded-xl bg-blue-500 px-4 py-1.5 text-xs font-bold text-white hover:bg-blue-600">
									Detail
								</a>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="5" class="px-6 py-10 text-center text-gray-500">
								Data relasi belum tersedia
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
			$('#tblRelasi').DataTable({
				responsive: true,
				pageLength: 10,
				lengthChange: true,
				autoWidth: false,
				sort: false,

				dom: '<"row mb-1"<"col-md-6"B><"col-md-6 text-end"f>>' +
					'<"row"<"col-12"tr>>' +
					'<"row mt-3"<"col-md-5"i><"col-md-7"p>>',

				buttons: [{
						extend: 'excel',
						text: '<i class="fas fa-file-excel"></i> Excel',
						className: 'btn btn-success btn-sm',
						title: 'Relasi Master 360'
					},
					{
						extend: 'pdf',
						text: '<i class="fas fa-file-pdf"></i> PDF',
						className: 'btn btn-danger btn-sm',
						title: 'Relasi Master 360',
						orientation: 'landscape',
						pageSize: 'A4'
					}
				],

				language: {
					search: "Cari:",
					lengthMenu: "Tampilkan _MENU_ data",
					zeroRecords: "Data tidak ditemukan",
					info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
					infoEmpty: "Tidak ada data",
					paginate: {
						next: "›",
						previous: "‹"
					}
				}
			});
		});
	</script>
@endpush
