@extends('layouts.app')

@section('title', 'Detail Relasi Master')
@section('page_title', 'Detail Relasi Master')
@section('page_desc', 'Siapa saja penilai untuk karyawan ini')

@section('content')

	<div class="space-y-6">

		<div class="rounded-3xl border bg-white p-6 shadow-sm">
			<div class="flex items-center justify-between">
				<div>
					<h2 class="text-xl font-extrabold text-gray-900">{{ $karyawan->nama_karyawan }}</h2>
					<p class="text-sm text-gray-500">
						NIK: <span class="font-semibold">{{ $karyawan->nik }}</span> • {{ $karyawan->jabatan }}
					</p>
				</div>

				<a href="{{ route('admin.relasi-master.index') }}"
					class="rounded-2xl bg-gray-100 px-5 py-2 text-sm font-bold text-gray-800 hover:bg-gray-200">
					← Back
				</a>
			</div>
		</div>

		<div class="rounded-3xl border bg-white p-6 shadow-sm">
			{{-- FORM TAMBAH RELASI --}}
			<div class="mt-4 rounded-2xl border border-gray-200/70 bg-gray-50 p-4">
				<form method="POST" action="{{ route('admin.relasi-master.store', $karyawan->nik) }}"
					class="flex flex-col gap-3 md:flex-row md:items-end">
					@csrf

					<div class="flex-1">
						<label class="text-xs font-bold text-gray-600">NIK Penilai</label>
						<input type="text" name="nik_penilai" placeholder="Masukkan NIK penilai..."
							class="mt-1 w-full rounded-2xl border border-gray-200 bg-white px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
							required>
					</div>

					<button type="submit"
						class="rounded-2xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-indigo-700">
						+ Tambah
					</button>
				</form>

				@error('nik_penilai')
					<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
				@enderror
			</div>
			{{-- END FORM TAMBAH RELASI --}}
			<h3 class="text-lg font-extrabold text-gray-900">
				Dinilai oleh ({{ $penilai->count() }})
			</h3>
			<p class="mt-1 text-sm text-gray-500">
				Berikut daftar penilai berdasarkan mapping master relasi.
			</p>

			
			<div class="mt-4 overflow-hidden rounded-2xl border">
				<table class="w-full text-sm">
					<thead class="bg-gray-50 text-gray-600">
						<tr>
							<th class="px-4 py-3 text-left">NIK Penilai</th>
							<th class="px-4 py-3 text-left">Nama</th>
							<th class="px-4 py-3 text-left">Jabatan</th>
							<th class="px-4 py-3 text-left">#</th>
						</tr>
					</thead>
					<tbody class="divide-y">
						@forelse($penilai as $p)
							<tr class="hover:bg-gray-50">
								<td class="px-4 py-3 font-semibold text-gray-900">{{ $p->nik }}</td>
								<td class="px-4 py-3">{{ $p->nama_karyawan }}</td>
								<td class="px-4 py-3 text-gray-600">{{ $p->jabatan }}</td>
								<td class="px-4 py-3 text-right">
									<form method="POST" action="{{ route('admin.relasi-master.destroy', $karyawan->nik) }}"
										onsubmit="return confirm('Yakin hapus relasi ini?')">
										@csrf
										@method('DELETE')

										<input type="hidden" name="nik_penilai" value="{{ $p->nik }}">

										<button type="submit"
											class="rounded-xl bg-red-50 px-3 py-1.5 text-xs font-bold text-red-700 hover:bg-red-100">
											Hapus
										</button>
									</form>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="4" class="px-4 py-10 text-center text-gray-500">
									Belum ada mapping penilai untuk karyawan ini.
								</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>

		</div>

	</div>

@endsection
