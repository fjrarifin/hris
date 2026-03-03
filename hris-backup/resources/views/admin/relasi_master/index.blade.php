@extends('layouts.app')

@section('title', 'Relasi Master')
@section('page_title', 'Relasi Master')
@section('page_desc', 'Mapping siapa menilai siapa (master relasi)')

@section('content')

	<div class="space-y-6">

		<div class="rounded-3xl border bg-white p-6 shadow-sm">
			<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
				<div>
					<h2 class="text-xl font-extrabold text-gray-900">Relasi Penilaian (Master)</h2>
					<p class="text-sm text-gray-500">
						Menampilkan daftar karyawan dan total penilai yang terhubung pada master relasi.
					</p>
				</div>

				<form method="GET" class="flex items-center gap-2">
					<input type="text" name="q" value="{{ $q }}" placeholder="Cari NIK / nama / jabatan..."
						class="w-72 rounded-2xl border border-gray-200 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
					<button class="rounded-2xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white hover:bg-indigo-700">
						Cari
					</button>
				</form>
			</div>
		</div>

		<div class="overflow-hidden rounded-3xl border bg-white shadow-sm">
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-gray-600">
					<tr>
						<th class="px-5 py-3 text-left">NIK</th>
						<th class="px-5 py-3 text-left">Nama</th>
						<th class="px-5 py-3 text-left">Jabatan</th>
						<th class="px-5 py-3 text-center">Total Penilai</th>
						<th class="px-5 py-3 text-center">Aksi</th>
					</tr>
				</thead>
				<tbody class="divide-y">
					@forelse ($list as $row)
						<tr class="hover:bg-gray-50">
							<td class="px-5 py-3 font-semibold text-gray-900">{{ $row->nik }}</td>
							<td class="px-5 py-3">{{ $row->nama_karyawan }}</td>
							<td class="px-5 py-3 text-gray-600">{{ $row->jabatan }}</td>
							<td class="px-5 py-3 text-center">
								<span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">
									{{ $row->total_penilai }}
								</span>
							</td>
							<td class="px-5 py-3 text-right">
								<a href="{{ route('admin.relasi-master.detail', $row->nik) }}"
									class="rounded-xl bg-gray-100 px-4 py-2 text-xs font-bold text-gray-800 hover:bg-gray-200">
									Detail
								</a>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="5" class="px-5 py-10 text-center text-gray-500">
								Data tidak ditemukan.
							</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<div>
			{{ $list->links() }}
		</div>

	</div>

@endsection
