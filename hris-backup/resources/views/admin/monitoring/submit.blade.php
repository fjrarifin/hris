@extends('layouts.app')

@section('title', 'Submit Review')
@section('page_title', 'Submit Review')
@section('page_desc', 'Daftar reviewer yang sudah submit')

@section('content')
	<div class="rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm">
		<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
			<div>
				<h2 class="text-lg font-extrabold text-gray-900">Submit Review</h2>
				<p class="text-sm text-gray-500">Periode: {{ $periode }}</p>
			</div>

			<form method="GET" class="flex flex-col gap-2 sm:flex-row sm:items-center">
				<input type="month" name="periode" value="{{ $periode }}"
					class="rounded-xl border border-gray-200 px-3 py-2 text-sm">

				<input type="text" name="q" value="{{ $search }}" placeholder="Cari NIK / Nama..."
					class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">

				<button class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
					Filter
				</button>

				<a href="{{ route('admin.monitoring.index') }}?periode={{ $periode }}"
					class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-200">
					← Kembali
				</a>
			</form>
		</div>
	</div>

	<div class="mt-5 overflow-hidden rounded-3xl border border-gray-200/70 bg-white shadow-sm">
		<table class="w-full text-sm">
			<thead class="bg-gray-50 text-gray-600">
				<tr>
					<th class="px-4 py-3 text-left">NIK</th>
					<th class="px-4 py-3 text-left">Nama</th>
					<th class="px-4 py-3 text-left">Jabatan</th>
					<th class="px-4 py-3 text-right">Terakhir Submit</th>
				</tr>
			</thead>
			<tbody class="divide-y">
				@forelse($rows as $r)
					<tr class="hover:bg-gray-50">
						<td class="px-4 py-3 font-semibold">{{ $r->nik_penilai }}</td>
						<td class="px-4 py-3">{{ $r->nama_karyawan ?? '-' }}</td>
						<td class="px-4 py-3">{{ $r->jabatan ?? '-' }}</td>
						<td class="px-4 py-3 text-right text-xs text-gray-500">
							{{ \Carbon\Carbon::parse($r->submitted_at)->format('d M Y H:i') }}
						</td>
					</tr>
				@empty
					<tr>
						<td colspan="5" class="px-6 py-10 text-center text-gray-500">
							Belum ada reviewer yang submit di periode ini.
						</td>
					</tr>
				@endforelse
			</tbody>
		</table>
	</div>

	<div class="mt-6">
		{{ $rows->links() }}
	</div>
@endsection
