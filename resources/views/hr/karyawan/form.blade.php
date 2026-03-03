@extends('layouts.app')

@section('title', 'Edit Karyawan')
@section('page-title', 'Edit Karyawan')

@section('content')

	<style>
		.tab-btn {
			background: #f3f4f6;
			color: #6b7280;
			border: 1px solid transparent;
			transition: 0.2s;
		}

		.tab-btn:hover {
			background: #e5e7eb;
		}

		.active-tab {
			background: white !important;
			color: #111827 !important;
			border: 1px solid #e5e7eb !important;
			border-bottom: 1px solid white !important;
		}
	</style>

	{{-- HEADER --}}
	<div class="card-outline card-primary mb-2 rounded-3xl bg-white p-4 shadow-sm">

		<div class="flex flex-col items-center text-center md:flex-row md:items-center md:justify-between md:text-left">

			{{-- LEFT SIDE (Foto + Info) --}}
			<div class="flex flex-col items-center gap-6 md:flex-row md:items-center md:gap-8">

				{{-- FOTO --}}
				<div class="flex-shrink-0">
					@if (optional($data->user)->photo)
						<img src="{{ asset('storage/' . $data->user->photo) }}" class="rounded-circle shadow" width="100" height="100"
							style="object-fit:cover;">
					@else
						<div
							class="flex h-24 w-24 items-center justify-center rounded-full bg-indigo-600 text-3xl font-bold text-white shadow-lg md:h-32 md:w-32 md:text-4xl">
							{{ strtoupper(substr($data->nama_karyawan, 0, 1)) }}
						</div>
					@endif
				</div>

				{{-- INFO --}}
				<div>
					<h2 class="text-left text-xl font-extrabold text-gray-900 md:text-left md:text-xl">
						{{ $data->nama_karyawan }}
					</h2>

					<div class="mt-4 flex flex-col items-center gap-2 text-sm text-gray-500 md:flex-row md:items-center md:gap-6">

						<span>
							NIK: <b>{{ $data->nik }}</b>
						</span>

					</div>
					<div class="flex flex-col items-center gap-2 text-sm text-gray-500 md:flex-row md:items-center md:gap-6">

						<span>
							Bergabung sejak
							<b>{{ \Carbon\Carbon::parse($data->join_date)->format('d M Y') }}</b>
						</span>

					</div>
				</div>

			</div>

			{{-- RIGHT SIDE (Status) --}}
			<div class="mt-4 md:mt-0">
				<span class="rounded-full bg-green-100 px-6 py-2 text-sm font-bold text-green-700">
					{{ $data->status_karyawan ?? 'AKTIF' }}
				</span>
			</div>

		</div>
	</div>

	{{-- TAB NAVIGATION --}}
	<div class="relative mt-2">

		{{-- TAB NAV --}}
		<div class="flex gap-2 px-2">

			<button onclick="openTab('tab-info')" id="btn-info" class="tab-btn rounded-t-xl px-6 py-2 text-sm font-semibold">
				Info
			</button>

			<button onclick="openTab('tab-kontrak')" id="btn-kontrak"
				class="tab-btn rounded-t-xl px-6 py-2 text-sm font-semibold">
				Kontrak
			</button>

		</div>

		{{-- CONTENT --}}
		<div class="rounded-xl bg-white p-4 shadow-sm">
			<form method="POST" action="{{ route('hr.karyawan.update', $data->nik) }}">
				@csrf
				@method('PUT')
				{{-- DATA UTAMA --}}
				<div id="tab-info" class="tab-content">
					<div class="mb-3 p-1">
						<h3 class="mb-4 text-base font-extrabold text-gray-900">
							Informasi Karyawan
						</h3>

						<div class="grid grid-cols-1 gap-4 md:grid-cols-2">

							{{-- NAMA --}}
							<div>
								<label class="text-xs font-bold text-gray-600">Nama Karyawan</label>
								<input type="text" name="nama_karyawan" value="{{ old('nama_karyawan', $data->nama_karyawan) }}"
									class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
							</div>

							{{-- JABATAN --}}
							<div>
								<label class="text-xs font-bold text-gray-600">Jabatan</label>
								<input type="text" name="jabatan" value="{{ old('jabatan', $data->jabatan) }}"
									class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
							</div>

							{{-- POSISI --}}
							<div>
								<label class="text-xs font-bold text-gray-600">Posisi</label>
								<input type="text" name="posisi" value="{{ old('posisi', $data->posisi) }}"
									class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
							</div>

							{{-- DIVISI --}}
							<div>
								<label class="text-xs font-bold text-gray-600">Divisi</label>
								<input type="text" name="divisi" value="{{ old('divisi', $data->divisi) }}"
									class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
							</div>

							{{-- DEPARTEMENT --}}
							<div>
								<label class="text-xs font-bold text-gray-600">Departement</label>
								<input type="text" name="departement" value="{{ old('departement', $data->departement) }}"
									class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
							</div>

							{{-- UNIT --}}
							<div>
								<label class="text-xs font-bold text-gray-600">Unit</label>
								<input type="text" name="unit" value="{{ old('unit', $data->unit) }}"
									class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
							</div>

							{{-- ATASAN --}}
							<div>
								<label class="text-xs font-bold text-gray-600">Atasan Langsung</label>
								<input type="text" name="nama_atasan_langsung"
									value="{{ old('nama_atasan_langsung', $data->nama_atasan_langsung) }}"
									class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
							</div>

							{{-- NO HP --}}
							<div>
								<label class="text-xs font-bold text-gray-600">No. HP</label>
								<input type="text" name="no_hp" value="{{ old('no_hp', $data->no_hp) }}"
									class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
							</div>

							{{-- JENIS KELAMIN --}}
							<div>
								<label class="text-xs font-bold text-gray-600">Jenis Kelamin</label>
								<select name="jenis_kelamin"
									class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
									<option value="">- Pilih -</option>
									<option value="L" {{ old('jenis_kelamin', $data->jenis_kelamin) === 'L' ? 'selected' : '' }}>
										Laki-laki
									</option>
									<option value="P" {{ old('jenis_kelamin', $data->jenis_kelamin) === 'P' ? 'selected' : '' }}>
										Perempuan
									</option>
								</select>
							</div>

						</div>
					</div>

					{{-- ACTION --}}
					<div class="mt-6 border-t pt-6">

						<div class="justify-end flex w-full flex-col gap-3 md:flex-row md:items-center md:justify-end">

							<a href="{{ route('hr.karyawan.index') }}"
								class="rounded-xl bg-gray-100 px-6 py-2 text-center text-sm font-semibold transition hover:bg-gray-200">
								Batal
							</a>

							<button type="submit"
								class="rounded-xl bg-indigo-600 px-6 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">
								Simpan Perubahan
							</button>

						</div>

					</div>
				</div>
			</form>

			{{-- RIWAYAT KONTRAK --}}
			@if ($kontrak->count())
				<div id="tab-kontrak" class="tab-content hidden">
					<div class="p-1">
						<h3 class="mb-3 text-base font-extrabold text-gray-900">
							Riwayat Kontrak
						</h3>

						<div class="overflow-hidden rounded-xl border">
							<table class="w-full text-xs">
								<thead class="bg-gray-50 text-gray-600">
									<tr>
										<th class="px-3 py-2">Kontrak Ke</th>
										<th class="px-3 py-2">Status</th>
										<th class="px-3 py-2">Start</th>
										<th class="px-3 py-2">End</th>
									</tr>
								</thead>
								<tbody>
									@foreach ($kontrak as $k)
										<tr class="border-t">
											<td class="px-3 py-2">{{ $k->kontrak_ke }}</td>
											<td class="px-3 py-2 font-bold">{{ $k->status_kontrak }}</td>
											<td class="px-3 py-2">{{ $k->start_date }}</td>
											<td class="px-3 py-2">{{ $k->end_date }}</td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					</div>
				</div>
			@endif


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
	</script>
@endpush
