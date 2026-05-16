@extends('layouts.app')

@section('title', 'Dasbor')
@section('page-title', 'Dasbor')

@section('content')

	{{-- Filter Section --}}
	<div class="card-outline card-primary mb-4 rounded-2xl bg-white p-4 shadow-sm">
		<form method="GET" action="{{ route('hr.360.index') }}" class="flex flex-wrap items-end gap-3">

			{{-- Filter Periode --}}
			<div class="min-w-[200px] flex-1">
				<label class="mb-2 block text-sm font-semibold text-gray-700">
					<i class="fas fa-calendar-alt mr-1 text-indigo-600"></i>
					Periode
				</label>
				<input type="month" name="periode" value="{{ $periode }}"
					class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
			</div>

			{{-- Filter Kategori --}}
			<div class="min-w-[200px] flex-1">
				<label class="mb-2 block text-sm font-semibold text-gray-700">
					<i class="fas fa-filter mr-1 text-indigo-600"></i>
					Kategori
				</label>
				<select name="kategori" class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
					<option value="">Semua Kategori</option>
					<option value="Sangat Baik" {{ $kategori == 'Sangat Baik' ? 'selected' : '' }}>Sangat Baik</option>
					<option value="Baik" {{ $kategori == 'Baik' ? 'selected' : '' }}>Baik</option>
					<option value="Cukup" {{ $kategori == 'Cukup' ? 'selected' : '' }}>Cukup</option>
					<option value="Kurang" {{ $kategori == 'Kurang' ? 'selected' : '' }}>Kurang</option>
					<option value="Sangat Kurang" {{ $kategori == 'Sangat Kurang' ? 'selected' : '' }}>Sangat Kurang</option>
				</select>
			</div>

			{{-- Filter Search --}}
			<div class="min-w-[200px] flex-1">
				<label class="mb-2 block text-sm font-semibold text-gray-700">
					<i class="fas fa-search mr-1 text-indigo-600"></i>
					Cari NIK/Nama/Posisi
				</label>
				<input type="text" name="q" value="{{ $search }}" placeholder="Ketik untuk mencari..."
					class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
			</div>

			{{-- Tombol Action --}}
			<div class="flex gap-2">
				<button type="submit"
					class="rounded-xl bg-indigo-600 px-6 py-2.5 font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
					<i class="fas fa-search mr-1"></i>
					Filter
				</button>
				<a href="{{ route('hr.360.index') }}"
					class="rounded-xl bg-gray-100 px-6 py-2.5 font-semibold text-gray-700 shadow-sm transition hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
					<i class="fas fa-redo mr-1"></i>
					Reset
				</a>
			</div>

		</form>
	</div>

	{{-- Info Badge --}}
	@if ($periode || $kategori || $search || $status)
		<div class="mb-4 flex flex-wrap items-center gap-2">
			<span class="text-sm font-semibold text-gray-700">Filter Aktif:</span>

			@if ($periode)
				<span
					class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-800">
					<i class="fas fa-calendar-alt"></i>
					{{ \Carbon\Carbon::parse($periode)->isoFormat('MMMM YYYY') }}
					<a href="{{ request()->fullUrlWithQuery(['periode' => null]) }}"
						class="ml-1 text-indigo-600 hover:text-indigo-900">
						<i class="fas fa-times"></i>
					</a>
				</span>
			@endif

			@if ($kategori)
				<span
					class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
					<i class="fas fa-star"></i>
					{{ $kategori }}
					<a href="{{ request()->fullUrlWithQuery(['kategori' => null]) }}" class="ml-1 text-green-600 hover:text-green-900">
						<i class="fas fa-times"></i>
					</a>
				</span>
			@endif

			@if ($status)
				<span
					class="{{ $status == 'submit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold">
					<i class="fas {{ $status == 'submit' ? 'fa-check-circle' : 'fa-exclamation-circle' }}"></i>
					{{ $status == 'submit' ? 'Sudah Submit' : 'Belum Submit' }}
					<a href="{{ request()->fullUrlWithQuery(['status' => null]) }}" class="ml-1 hover:opacity-70">
						<i class="fas fa-times"></i>
					</a>
				</span>
			@endif

			@if ($search)
				<span
					class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-3 py-1 text-xs font-semibold text-yellow-800">
					<i class="fas fa-search"></i>
					"{{ $search }}"
					<a href="{{ request()->fullUrlWithQuery(['q' => null]) }}" class="ml-1 text-yellow-600 hover:text-yellow-900">
						<i class="fas fa-times"></i>
					</a>
				</span>
			@endif
		</div>
	@endif

	{{-- SUBMIT REVIEW --}}
	<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
		{{-- Sudah Submit --}}
		<div class="card-outline card-primary rounded-2xl bg-white p-4 shadow-sm">
			<p class="text-xs text-gray-500">Total Submit Periode {{ \Carbon\Carbon::parse($periode)->isoFormat('MMMM YYYY') }}
			</p>
			<div class="mt-1 flex items-center justify-between">
				<p class="text-lg font-extrabold text-green-600">
					{{ $totalSubmit }}
				</p>
				<button onclick="openModal('submit')"
					class="rounded-xl bg-green-600 px-3 py-2 text-xs font-bold text-white hover:bg-green-700">
					<i class="fas fa-check-circle mr-1"></i> Detail
				</button>
			</div>
			<div class="mt-2 text-xs text-gray-500">
				<i class="fas fa-info-circle mr-1"></i>
				Karyawan yang sudah dinilai lengkap (100%)
			</div>
		</div>

		{{-- Belum Submit --}}
		<div class="card-outline card-primary rounded-2xl bg-white p-4 shadow-sm">
			<p class="text-xs text-gray-500">Total Belum Submit Periode
				{{ \Carbon\Carbon::parse($periode)->isoFormat('MMMM YYYY') }}</p>
			<div class="mt-1 flex items-center justify-between">
				<p class="text-lg font-extrabold text-red-600">
					{{ $totalBelumSubmit }}
				</p>
				<button onclick="openModal('belum')"
					class="rounded-xl bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-700">
					<i class="fas fa-exclamation-circle mr-1"></i> Detail
				</button>
			</div>
			<div class="mt-2 text-xs text-gray-500">
				<i class="fas fa-info-circle mr-1"></i>
				Karyawan yang penilaiannya belum lengkap (&lt;100%)
			</div>
		</div>
	</div>

	{{-- DETAIL MODAL --}}
	<div id="detailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4">

		<div class="flex max-h-[80vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
			<div class="flex items-center justify-between border-b bg-white px-5 py-3">
				<h3 id="modalTitle" class="text-lg font-extrabold"></h3>
				<button onclick="closeModal()" class="text-gray-500 hover:text-black">✕</button>
			</div>
			{{-- Modal Body --}}

			<div class="flex-1 overflow-y-auto p-5">
				<table class="w-full text-sm">
					<thead class="top-0 bg-white">
						<tr class="border-b text-left">
							<th class="py-2">NIK</th>
							<th>Nama</th>
							<th>Jabatan</th>
							<th id="thWaktu">Waktu Submit</th>
						</tr>
					</thead>
					<tbody id="modalBody"></tbody>
				</table>
			</div>
			{{-- End Modal Body --}}
		</div>
	</div>


	{{-- PIE CHART + SUMMARY --}}
	<div class="mt-3 grid max-w-full gap-5 md:grid-cols-3">


		{{-- PIE CHART --}}
		<div class="card-outline card-primary overflow-hidden rounded-3xl bg-white p-4 shadow-sm md:p-6">


			<div class="flex items-start justify-between">
				<div>
					<h3 class="text-base font-extrabold text-gray-900">Distribusi Kategori</h3>
					<p class="text-xs text-gray-500">Periode: {{ $periode }}</p>
				</div>
				<span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">
					Total: {{ $totalPie }}
				</span>
			</div>

			<div class="mt-4">
				<canvas id="pieKategoriChart" height="350"></canvas>
			</div>
		</div>

		{{-- SUMMARY LIST --}}
		<div class="card-outline card-primary overflow-hidden rounded-3xl bg-white p-4 shadow-sm md:p-6">


			<h3 class="text-base font-extrabold text-gray-900">Ringkasan</h3>
			<p class="text-xs text-gray-500">Persentase kategori berdasarkan Avg Score</p>

			<div class="mt-4 space-y-3">
				@forelse ($pieSummary as $p)
					@php
						$percent = $totalPie > 0 ? round(($p->total / $totalPie) * 100, 1) : 0;

						// Warna badge berdasarkan kategori
						$badgeClass = match ($p->label) {
						    'Sangat Baik' => 'bg-green-50 border-green-200',
						    'Baik' => 'bg-blue-50 border-blue-200',
						    'Cukup' => 'bg-yellow-50 border-yellow-200',
						    'Kurang' => 'bg-orange-50 border-orange-200',
						    'Sangat Kurang' => 'bg-red-50 border-red-200',
						    default => 'bg-gray-50 border-gray-200',
						};
					@endphp
					<div class="{{ $badgeClass }} flex items-center justify-between gap-3 rounded-2xl border-2 px-3 py-2">
						<div class="flex-1">
							<p class="text-sm font-bold text-gray-900">{{ $p->label }}</p>
							<p class="text-xs text-gray-500">
								{{ $p->total }} orang ({{ $percent }}%)
							</p>
						</div>

						<a href="{{ request()->fullUrlWithQuery(['kategori' => $p->label, 'page' => 1]) }}"
							class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-indigo-700">
							Detail
						</a>
					</div>
				@empty
					<p class="py-4 text-center text-sm text-gray-500">Belum ada data kategori untuk periode ini.</p>
				@endforelse
			</div>
		</div>

		{{-- TOP SCORES --}}
		<div class="card-outline card-primary overflow-hidden rounded-3xl bg-white p-4 shadow-sm md:p-6">


			<h3 class="text-base font-extrabold text-gray-900">Score Tertinggi</h3>
			<p class="text-xs text-gray-500">
				Score tertinggi berdasarkan Avg Score
				<span class="font-semibold">
					({{ \Carbon\Carbon::parse($periode)->isoFormat('MMMM YYYY') }})
				</span>
			</p>

			<div class="mt-4 space-y-3 text-xs">
				@forelse ($topScores as $i => $row)
					<div class="flex items-center justify-between rounded-2xl border border-gray-200/70 bg-white px-4 py-3 shadow-sm">

						{{-- Kiri --}}
						<div class="flex min-w-0 items-center gap-3">
							<div
								class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-sm font-extrabold text-indigo-700">
								{{ $i + 1 }}
							</div>

							<div class="min-w-0">
								<p class="truncate text-sm font-extrabold text-gray-900">
									{{ $row->nama_karyawan }}
								</p>
								<p class="truncate text-xs text-gray-500">
									{{ $row->jabatan }}
								</p>
							</div>
						</div>

						{{-- Kanan --}}
						<div class="text-right">
							<p class="text-md font-extrabold leading-none text-indigo-600">
								{{ number_format($row->avg_score, 2) }}
							</p>
							<p class="mt-0.5 text-[11px] text-gray-400">
								Avg Score
							</p>
						</div>

					</div>
				@empty
					<p class="text-center text-sm text-gray-400">
						Belum ada data penilaian
					</p>
				@endforelse
			</div>

		</div>


	</div>

	{{-- Table --}}
	<div class="card-outline card-primary mt-3 overflow-hidden rounded-3xl bg-white shadow-sm">
		<div class="p-4">
			<table id="tblMonitoring" class="table-bordered table-striped table-hover table w-full text-xs">
				<thead class="bg-gray-50 text-gray-600">
					<tr>
						<th class="px-4 py-2 text-center font-bold">NIK</th>
						<th class="px-4 py-2 text-center font-bold">Nama</th>
						<th class="px-4 py-2 text-center font-bold">Jabatan</th>
						<th class="px-4 py-2 text-center font-bold">Posisi</th>
						<th class="px-4 py-2 text-center font-bold">Progress</th>
						<th class="px-4 py-2 text-center font-bold">Score</th>
						<th class="px-4 py-2 text-center font-bold">Kategori</th>
					</tr>
				</thead>

				<tbody>
					@forelse ($rows as $r)
						<tr>
							<td class="px-2 py-2 text-xs font-semibold text-gray-700">
								{{ $r->nik_relasi }}
							</td>

							<td class="px-2 py-2">
								<p class="font-bold text-gray-900">
									{{ $r->nama_karyawan ?? '(Tidak ditemukan di master)' }}
								</p>
							</td>

							<td class="px-2 py-2 text-xs text-gray-600">
								{{ $r->jabatan ?? '-' }}
							</td>

							<td class="px-2 py-2 text-xs text-gray-600">
								{{ $r->posisi ?? '-' }}
							</td>

							<td class="px-2 py-2">
								<span class="ml-2 font-semibold text-gray-700">
									{{ $r->progress_percent }}%
								</span>
							</td>

							<td class="px-2 py-2 text-center font-extrabold text-indigo-700">
								{{ $r->avg_score }}
							</td>

							<td class="px-6 py-2 text-center">
								@php
									$badgeClass = match ($r->kategori) {
									    'Sangat Baik' => 'bg-green-100 text-green-800',
									    'Baik' => 'bg-blue-100 text-blue-800',
									    'Cukup' => 'bg-yellow-100 text-yellow-800',
									    'Kurang' => 'bg-orange-100 text-orange-800',
									    'Sangat Kurang' => 'bg-red-100 text-red-800',
									    default => 'bg-gray-100 text-gray-800',
									};
								@endphp
								<span class="{{ $badgeClass }} rounded-full px-3 py-1 text-xs font-semibold">
									{{ $r->kategori }}
								</span>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500">
								Data monitoring belum tersedia. Coba ubah periode / pencarian.
							</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

	{{-- Chart.js Scripts --}}
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

	<script>
		document.addEventListener("DOMContentLoaded", function() {
			const ctx = document.getElementById('pieKategoriChart');
			if (ctx) {
				const labels = @json($pieLabels);
				const values = @json($pieValues);
				const total = values.reduce((a, b) => a + b, 0);

				new Chart(ctx, {
					type: 'pie',
					data: {
						labels: labels,
						datasets: [{
							data: values,
							backgroundColor: [
								'#ef4444', // Sangat Kurang - Red
								'#f97316', // Kurang - Orange
								'#eab308', // Cukup - Yellow
								'#3b82f6', // Baik - Blue
								'#22c55e', // Sangat Baik - Green
							],
							borderWidth: 2,
							borderColor: '#fff'
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: {
								position: 'bottom',
								labels: {
									padding: 15,
									font: {
										size: 12
									}
								}
							},
							tooltip: {
								callbacks: {
									label: function(context) {
										const val = context.raw || 0;
										const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
										return `${context.label}: ${val} orang (${pct}%)`;
									}
								}
							},
							datalabels: {
								color: '#fff',
								font: {
									weight: 'bold',
									size: 10
								},
								formatter: function(value, ctx) {
									const label = ctx.chart.data.labels[ctx.dataIndex];
									const pct = (value / total) * 100;
									return pct >= 5 ? `${label}\n${pct.toFixed(1)}%` : '';
								}
							}
						}
					},
					plugins: [ChartDataLabels]
				});
			}
		});
	</script>

	<script>
		function openModal(type) {
			const modal = document.getElementById('detailModal');
			const body = document.getElementById('modalBody');
			const title = document.getElementById('modalTitle');
			const thWaktu = document.getElementById('thWaktu');

			body.innerHTML = '<tr><td colspan="4">Loading...</td></tr>';

			let url = '';
			if (type === 'submit') {
				title.innerText = 'Karyawan Sudah Submit';
				thWaktu.style.display = '';
				url = '/hr/monitoring/360/modal-submit?periode={{ $periode }}';
			} else {
				title.innerText = 'Karyawan Belum Submit';
				thWaktu.style.display = 'none';
				url = '/hr/monitoring/360/modal-belum-submit?periode={{ $periode }}';
			}

			fetch(url)
				.then(res => res.json())
				.then(rows => {
					body.innerHTML = rows.length ?
						rows.map(r => `
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 font-mono text-xs">${r.nik}</td>
                            <td class="font-semibold">${r.nama_karyawan}</td>
                            <td class="text-gray-600">${r.jabatan}</td>
                            ${type === 'submit'
                                ? `<td class="text-xs text-gray-500">${r.waktu_submit}</td>`
                                : ``}
                        </tr>
                        `)
						.join('') :
						'<tr><td colspan="4">Tidak ada data</td></tr>';
				});

			modal.classList.remove('hidden');
			modal.classList.add('flex');
		}

		function closeModal() {
			document.getElementById('detailModal').classList.add('hidden');
		}
	</script>

@endsection

@push('scripts')
	<script>
		$(document).ready(function() {
			$("#tblMonitoring").DataTable({
				responsive: true,
				lengthChange: true,
				autoWidth: false,
				pageLength: 10,
				sort: false,
				order: [
					[5, 'desc']
				], // Sort by Avg Score descending

				language: {
					search: "Cari:",
					lengthMenu: "Tampilkan _MENU_ data per halaman",
					zeroRecords: "Data tidak ditemukan",
					info: "Menampilkan halaman _PAGE_ dari _PAGES_",
					infoEmpty: "Tidak ada data tersedia",
					infoFiltered: "(difilter dari _MAX_ total data)",
					paginate: {
						first: "Pertama",
						last: "Terakhir",
						next: "Selanjutnya",
						previous: "Sebelumnya"
					}
				},

				dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>' +
					'<"row"<"col-sm-12"tr>>' +
					'<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',

				buttons: [
					// {
					// 	extend: 'copy',
					// 	text: '<i class="fas fa-copy"></i> Copy',
					// 	className: 'btn btn-sm btn-secondary',
					// 	exportOptions: {
					// 		columns: [0, 1, 2, 3, 5, 6]
					// 	}
					// },
					// {
					// 	extend: 'csv',
					// 	text: '<i class="fas fa-file-csv"></i> CSV',
					// 	className: 'btn btn-sm btn-success',
					// 	exportOptions: {
					// 		columns: [0, 1, 2, 3, 5, 6]
					// 	}
					// },
					{
						extend: 'excel',
						text: '<i class="fas fa-file-excel"></i> Excel',
						className: 'btn btn-sm btn-success',
						title: 'Monitoring Penilaian 360 - Periode {{ $periode }}',
						exportOptions: {
							columns: [0, 1, 2, 3, 5, 6]
						}
					},
					{
						extend: 'pdf',
						text: '<i class="fas fa-file-pdf"></i> PDF',
						className: 'btn btn-sm btn-danger',
						title: 'Monitoring Penilaian 360',
						orientation: 'landscape',
						pageSize: 'A4',
						exportOptions: {
							columns: [0, 1, 2, 3, 5, 6]
						},
						customize: function(doc) {
							doc.content[0].text =
								'Monitoring Penilaian 360\nPeriode: {{ $periode }}';
							doc.content[0].alignment = 'center';
							doc.styles.tableHeader.fillColor = '#4f46e5';
						}
					}
					// {
					// 	extend: 'print',
					// 	text: '<i class="fas fa-print"></i> Print',
					// 	className: 'btn btn-sm btn-info',
					// 	title: 'Monitoring Penilaian 360 - Periode {{ $periode }}',
					// 	exportOptions: {
					// 		columns: [0, 1, 2, 3, 5, 6]
					// 	}
					// },
					// {
					// 	extend: 'colvis',
					// 	text: '<i class="fas fa-columns"></i> Kolom',
					// 	className: 'btn btn-sm btn-secondary'
					// }
				],

				columnDefs: [{
						targets: 4, // Progress column
						orderable: false,
						searchable: false
					},
					{
						targets: 5, // Avg Score column
						className: 'text-center font-weight-bold'
					},
					{
						targets: 6, // Kategori column
						className: 'text-center'
					}
				]
			});
		});
	</script>
@endpush
