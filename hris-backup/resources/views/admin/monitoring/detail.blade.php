@extends('layouts.app')

@section('title', 'Detail Monitoring')
@section('page_title', 'Detail Monitoring')
@section('page_desc', 'Siapa yang sudah / belum menilai')

@section('content')
	<div class="rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm">
		<div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
			<div>
				<h2 class="text-lg font-extrabold text-gray-900">
					{{ $karyawan->nama_karyawan ?? 'Karyawan' }}
				</h2>
				<p class="text-sm text-gray-500">
					NIK: {{ $nik }} • {{ $karyawan->jabatan ?? '-' }}
				</p>

				<p class="mt-3 text-sm text-gray-700">
					Periode: <b>{{ $periode }}</b>
				</p>
			</div>

			<div class="rounded-2xl bg-indigo-50 px-4 py-3 text-right">
				<p class="text-xs font-bold text-indigo-700">Nilai Akhir</p>
				<p class="text-2xl font-extrabold text-indigo-700">{{ $avgFinal }}</p>
				<p class="text-xs text-gray-600">{{ $kategori }}</p>
			</div>
		</div>

		<div class="mt-4">
			<div class="flex items-center justify-between text-sm font-semibold text-gray-700">
				<span>Progress Penilaian</span>
				<span>{{ $totalDone }}/{{ $totalExpected }} ({{ $progressPercent }}%)</span>
			</div>

			<div class="mt-2 h-2 w-full rounded-full bg-gray-100">
				<div class="h-2 rounded-full bg-indigo-600" style="width: {{ $progressPercent }}%"></div>
			</div>
		</div>
	</div>

	{{-- SUDAH MENILAI --}}
	<div class="mt-5 rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm">
		<h3 class="text-base font-extrabold text-gray-900">✅ Sudah Menilai ({{ $donePenilai->count() }})</h3>
		<p class="text-sm text-gray-500">Berikut penilai yang sudah submit di periode ini</p>

		<div class="mt-4 space-y-3">
			@forelse ($donePenilai as $p)
				<div class="flex items-center justify-between gap-3 rounded-2xl border border-gray-200/70 p-4">
					<div>
						<p class="text-sm font-bold text-gray-900">
							{{ $p->nama_karyawan ?? $p->nik_penilai }}
						</p>
						<p class="text-xs text-gray-500">{{ $p->nik_penilai }} • {{ $p->jabatan ?? '-' }}</p>
						@if(!empty($p->catatan))
							<p class="text-xs text-gray-500 mt-1">{{ $p->catatan }}</p>
						@endif
					</div>

					<div class="text-right">
						<p class="text-sm font-extrabold text-indigo-700">{{ $p->avg_score }}</p>
						<p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($p->submitted_at)->format('d M Y H:i') }}</p>
					</div>
				</div>
			@empty
				<div class="rounded-2xl bg-gray-50 p-4 text-sm text-gray-600">
					Belum ada penilai yang submit di periode ini.
				</div>
			@endforelse
		</div>
	</div>

	{{-- BELUM MENILAI --}}
	<div class="mt-5 rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm">
		<h3 class="text-base font-extrabold text-gray-900">⏳ Belum Menilai ({{ $notDonePenilai->count() }})</h3>
		<p class="text-sm text-gray-500">Expected penilai berdasarkan mapping relasi</p>

		<div class="mt-4 space-y-3">
			@forelse ($notDonePenilai as $p)
				<div class="flex items-center justify-between gap-3 rounded-2xl border border-gray-200/70 p-4">
					<div>
						<p class="text-sm font-bold text-gray-900">
							{{ $p->nama_karyawan ?? $p->nik_penilai }}
						</p>
						<p class="text-xs text-gray-500">
							{{ $p->nik_penilai }} • {{ $p->jabatan ?? '-' }}
						</p>
					</div>
				</div>
			@empty
				<div class="rounded-2xl bg-green-50 p-4 text-sm font-semibold text-green-700">
					Semua penilai sudah submit 🎉
				</div>
			@endforelse
		</div>
	</div>

	<div class="mt-6 flex justify-end">
		<a href="{{ route('admin.monitoring.index') }}?periode={{ $periode }}"
			class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-200">
			← Kembali
		</a>
	</div>
@endsection
