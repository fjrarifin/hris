@extends('layouts.app')

@section('title', 'Dasbor')
@section('page-title', 'Dasbor')

@section('content')

	{{-- Filter Section --}}
	<div class="card-outline card-primary mb-4 rounded-2xl bg-white p-4 shadow-sm">
		<form method="GET" action="{{ route('hr.sa.index') }}" class="flex flex-wrap items-end gap-3">

			{{-- Filter Periode --}}
			<div class="min-w-[200px] flex-1">
				<label class="mb-2 block text-sm font-semibold text-gray-700">
					<i class="fas fa-calendar-alt mr-1 text-indigo-600"></i>
					Periode
				</label>
				<input type="month" name="periode" value="{{ $periode }}"
					class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
			</div>

			{{-- Tombol Action --}}
			<div class="flex gap-2">
				<button type="submit"
					class="rounded-xl bg-indigo-600 px-6 py-2.5 font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
					<i class="fas fa-search mr-1"></i>
					Filter
				</button>
				<a href="{{ route('hr.sa.index') }}"
					class="rounded-xl bg-gray-100 px-6 py-2.5 font-semibold text-gray-700 shadow-sm transition hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
					<i class="fas fa-redo mr-1"></i>
					Reset
				</a>
			</div>

		</form>
	</div>

	{{-- Info Badge --}}
	@if ($periode)
		<div class="mb-4 flex flex-wrap items-center gap-2">
			<span class="text-sm font-semibold text-gray-700">Filter Aktif:</span>

			@if ($periode)
				<span
					class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-800">
					<i class="fas fa-calendar-alt"></i>
					{{ \Carbon\Carbon::parse($periode)->isoFormat('MMMM YYYY') }}
					<a href="{{ request()->fullUrlWithQuery(['periode' => null]) }}" class="ml-1 text-indigo-600 hover:text-indigo-900">
						<i class="fas fa-times"></i>
					</a>
				</span>
			@endif
		</div>
	@endif

	{{-- Summary Card --}}
	<div class="grid grid-cols-1 gap-4 md:grid-cols-3">
		<div class="rounded-2xl bg-white p-4 shadow">
			<p class="text-xs text-gray-500">Total Karyawan</p>
			<p class="text-xl font-extrabold">{{ $totalKaryawan }}</p>
		</div>

		<div class="rounded-2xl bg-white p-4 shadow">
			<p class="text-xs text-gray-500">Sudah Submit</p>
			<p class="text-xl font-extrabold text-green-600">{{ $sudahSubmit }}</p>
		</div>

		<div class="rounded-2xl bg-white p-4 shadow">
			<p class="text-xs text-gray-500">Belum Submit</p>
			<p class="text-xl font-extrabold text-red-600">{{ $belumSubmit }}</p>
		</div>
	</div>

	{{-- DETAIL MODAL --}}
	<div id="detailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4">

		<div
			class="card-outline card-primary flex max-h-[80vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl">

			{{-- Modal Header --}}
			<div class="flex items-center justify-between border-b bg-white px-5 py-2">
				<h3 id="mNama" class="text-lg font-extrabold"></h3>
				<button onclick="closeModal()" class="text-gray-500 hover:text-black">✕</button>
			</div>
			{{-- Modal Body --}}

			<div class="flex-1 space-y-2 overflow-y-auto p-5 text-sm" id="modalContent">

				{{-- IDENTITAS --}}
				<div class="rounded-xl bg-gray-50 p-4">
					<p class="text-xs text-gray-600">
						<span id="mNik"></span> • <span id="mJabatan"></span>
					</p>
					<p class="mt-1 text-xs text-gray-500">
						Waktu Submit: <span id="mWaktu"></span>
					</p>
				</div>

				{{-- KESULITAN --}}
				<div class="rounded-xl border p-4">
					<h4 class="mb-2 text-sm font-extrabold text-gray-800">Kesulitan</h4>
					<div class="max-h-40 overflow-y-auto leading-relaxed text-gray-700">
						<p id="mKesulitan"></p>
					</div>
				</div>

				{{-- IMPROVEMENT --}}
				<div class="rounded-xl border p-4">
					<h4 class="mb-2 text-sm font-extrabold text-gray-800">Improvement</h4>
					<div class="max-h-40 overflow-y-auto leading-relaxed text-gray-700">
						<p id="mImprovement"></p>
					</div>
				</div>

				{{-- PERBAIKAN --}}
				<div class="rounded-xl border p-4">
					<h4 class="mb-2 text-sm font-extrabold text-gray-800">Perbaikan Hompimplay</h4>
					<div class="max-h-40 overflow-y-auto leading-relaxed text-gray-700">
						<p id="mPerbaikan"></p>
					</div>
				</div>

				{{-- CATATAN --}}
				<div class="rounded-xl border p-4">
					<h4 class="mb-2 text-sm font-extrabold text-gray-800">Catatan Rekan</h4>
					<div class="max-h-32 overflow-y-auto leading-relaxed text-gray-700">
						<p id="mCatatan"></p>
					</div>
				</div>

			</div>

			{{-- End Modal Body --}}
		</div>
	</div>

	{{-- Table --}}
	<div class="card-outline card-primary mt-3 overflow-hidden rounded-3xl bg-white shadow-sm">
		<div class="p-4">

			<table id="saMonitoring" class="table-bordered table-striped table-hover table w-full text-xs">
				<thead class="bg-gray-50 text-gray-600">
					<tr>
						<th class="px-4 py-2 text-center font-bold">NIK</th>
						<th class="px-4 py-2 text-left font-bold">Nama</th>
						<th class="px-4 py-2 text-left font-bold">Jabatan</th>
						<th class="px-4 py-2 text-center font-bold">Status</th>
						<th class="px-4 py-2 text-center font-bold">Aksi</th>
					</tr>
				</thead>

				<tbody>
					@foreach ($rows as $row)
						<tr>
							<td class="px-2 py-2 text-center font-semibold text-gray-700">
								{{ $row->nik }}
							</td>

							<td class="px-2 py-2">
								<p class="font-bold text-gray-900">
									{{ $row->nama_karyawan }}
								</p>
							</td>

							<td class="px-2 py-2 text-gray-600">
								{{ $row->jabatan ?? '-' }}
							</td>

							<td class="px-2 py-2 text-center">
								@if ($row->status === 'sudah')
									<span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">
										Sudah Submit
									</span>
								@else
									<span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">
										Belum Submit
									</span>
								@endif
							</td>

							<td class="px-2 py-2 text-center">
								@if ($row->status === 'sudah')
									<button data-nik="{{ $row->nik }}"
										class="btn-detail rounded-xl bg-indigo-600 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-700">
										Detail
									</button>
								@else
									<span class="text-xs text-gray-400">-</span>
								@endif
							</td>
						</tr>
					@endforeach
				</tbody>

			</table>
		</div>
	</div>

	<script>
		document.querySelectorAll('.btn-detail').forEach(btn => {
			btn.addEventListener('click', async () => {
				const nik = btn.dataset.nik;

				const baseUrl = "{{ route('hr.sa.detail', '__nik__') }}";

				const res = await fetch(
					baseUrl.replace('__nik__', nik) + `?periode={{ $periode }}`, {
						headers: {
							'X-Requested-With': 'XMLHttpRequest'
						}
					}
				);

				const d = await res.json();

				document.getElementById('mNama').textContent = d.nama_karyawan;
				document.getElementById('mNik').textContent = d.nik;
				document.getElementById('mJabatan').textContent = d.jabatan;
				document.getElementById('mWaktu').textContent = d.submitted_at ?? '-';

				document.getElementById('mKesulitan').textContent = d.kesulitan ?? '-';
				document.getElementById('mImprovement').textContent = d.improvement ?? '-';
				document.getElementById('mPerbaikan').textContent = d.perbaikan_hompimplay ?? '-';
				document.getElementById('mCatatan').textContent = d.catatan_rekan ?? '-';

				const modal = document.getElementById('detailModal');
				modal.classList.remove('hidden');
				modal.classList.add('flex');
			});
		});

		function closeModal() {
			const modal = document.getElementById('detailModal');
			modal.classList.add('hidden');
			modal.classList.remove('flex');
		}
	</script>


@endsection

@push('scripts')
	<script>
		var table = $("#saMonitoring").DataTable({
			responsive: true,
			autoWidth: true,
			pageLength: 10,
			ordering: true,
			order: [
				[1, 'asc']
			],
			dom: '<"flex flex-wrap items-center justify-between mb-3"' +
				'<"export-area">' +
				'<"dt-search"f>' +
				'>' +
				'<"overflow-x-auto"tr>' +
				'<"flex flex-wrap items-center justify-between mt-2"' +
				'<"text-sm"i>' +
				'<"pagination"p>' +
				'>',
			language: {
				search: "Cari:",
				lengthMenu: "Tampilkan _MENU_ data",
				zeroRecords: "Data tidak ditemukan",
				info: "Menampilkan _START_ - _END_ dari _TOTAL_",
				infoEmpty: "Tidak ada data",
				paginate: {
					previous: "‹",
					next: "›"
				}
			}
		});

		// Inject tombol export
		$('.export-area').html(`
			<a href="{{ route('hr.sa.export', ['periode' => $periode]) }}"
				class="rounded-xl bg-green-600 px-4 py-2 text-xs font-bold text-white hover:bg-green-700">
				<i class="fas fa-file-excel mr-1"></i> Ekspor Excel
			</a>
		`);
	</script>
@endpush
