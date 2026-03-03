@extends('layouts.app')

@section('title', 'Detail Relasi')
@section('page_title', 'Detail Relasi 360')
@section('page_desc', 'Siapa saja yang menilai karyawan ini')

@section('content')

	{{-- HEADER --}}
	<div class="card-outline card-primary mb-4 rounded-3xl bg-white p-6 shadow-sm">
		<div class="flex items-center justify-between">
			<div>
				<h2 class="text-lg font-extrabold text-gray-900">
					{{ $karyawan->nama_karyawan }}
				</h2>
				<p class="text-sm text-gray-500">
					NIK: <b>{{ $karyawan->nik }}</b> • {{ $karyawan->jabatan }}
				</p>
			</div>

			<a href="{{ route('hr.360.relasi') }}" class="rounded-xl bg-gray-100 px-5 py-2 text-sm font-bold hover:bg-gray-200">
				← Kembali
			</a>
		</div>
	</div>

	{{-- FORM TAMBAH --}}
	<div class="card-outline card-primary mb-4 rounded-3xl bg-white p-6 shadow-sm">
		<form method="POST" action="{{ route('hr.360.relasi.store', $karyawan->nik) }}"
			class="flex flex-col gap-3 md:flex-row md:items-end">
			@csrf

			<div class="flex-1">
				<label class="text-xs font-bold text-gray-600">
					Tambah NIK Penilai
				</label>
				<input type="text" name="nik_penilai"
					class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
					placeholder="Masukkan NIK penilai..." required>
			</div>

			<button class="rounded-xl bg-indigo-600 px-6 py-2 text-sm font-bold text-white hover:bg-indigo-700">
				+ Tambah
			</button>
		</form>
	</div>

	{{-- TABLE PENILAI --}}
	<div class="card-outline card-primary overflow-hidden rounded-3xl bg-white shadow-sm">
		<div class="p-4">
			<table class="table-bordered table-striped table-hover table w-full text-xs">
				<thead class="bg-gray-50 text-gray-600">
					<tr>
						<th class="px-4 py-2 font-bold">NIK Penilai</th>
						<th class="px-4 py-2 font-bold">Nama</th>
						<th class="px-4 py-2 font-bold">Jabatan</th>
						<th class="px-4 py-2 text-center font-bold">#</th>
					</tr>
				</thead>

				<tbody>
					@forelse ($penilai as $p)
						<tr>
							<td class="px-3 py-2 font-semibold">{{ $p->nik }}</td>
							<td class="px-3 py-2">{{ $p->nama_karyawan }}</td>
							<td class="px-3 py-2 text-gray-600">{{ $p->jabatan }}</td>
							<td class="px-3 py-2 text-center">
								<form method="POST" action="{{ route('hr.360.relasi.destroy', $karyawan->nik) }}"
									onsubmit="return confirm('Hapus relasi ini?')">
									@csrf
									@method('DELETE')
									<input type="hidden" name="nik_penilai" value="{{ $p->nik }}">
									<button class="rounded-lg bg-red-50 px-3 py-1 text-xs font-bold text-red-700 hover:bg-red-100">
										Hapus
									</button>
								</form>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="4" class="px-6 py-10 text-center text-gray-500">
								Belum ada penilai
							</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

@endsection
