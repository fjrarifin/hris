@extends('layouts.app')

@section('title', 'Monitoring Penilaian')
@section('page_title', 'Monitoring Penilaian')
@section('page_desc', 'Progress penilaian per karyawan')

@section('content')

	{{-- SUBMIT REVIEW --}}
	<div class="mt-5 rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm">
		<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
			<div>
				<h2 class="text-lg font-extrabold text-gray-900">Submit Review</h2>
				<p class="text-sm text-gray-500">Pantau progress reviewer</p>
			</div>
			<a href="{{ route('admin.monitoring.submit-review') }}?periode={{ $periode }}"
				class="inline-flex rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
				View
			</a>
		</div>
	</div>

	{{-- PIE CHART + SUMMARY --}}
	<div class="mt-5 grid gap-5 md:grid-cols-3">

		{{-- PIE CHART --}}
		<div class="rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm md:col-span-1">
			<div class="flex items-start justify-between">
				<div>
					<h3 class="text-base font-extrabold text-gray-900">Distribusi Kategori</h3>
					<p class="text-sm text-gray-500">Periode: {{ $periode }}</p>
				</div>
				<span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">
					Total: {{ $totalPie }}
				</span>
			</div>

			<div class="mt-4">
				<canvas id="pieKategoriChart" height="220"></canvas>
			</div>
		</div>

		{{-- SUMMARY LIST --}}
		<div class="rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm md:col-span-2">
			<h3 class="text-base font-extrabold text-gray-900">Ringkasan</h3>
			<p class="text-sm text-gray-500">Persentase kategori berdasarkan Avg Score</p>

			<div class="mt-4 grid gap-3 sm:grid-cols-2">
				@foreach ($pieSummary as $p)
					@php
						$percent = $totalPie > 0 ? round(($p->total / $totalPie) * 100, 1) : 0;
					@endphp
					<div class="flex items-center justify-between gap-3 rounded-2xl px-1 py-3 pl-4">
						<div>
							<p class="text-sm font-bold text-gray-900">{{ $p->label }}</p>
							<p class="text-xs text-gray-500">
								{{ $p->total }} orang ({{ $percent }}%)
							</p>
						</div>

						<a href="{{ request()->fullUrlWithQuery(['kategori' => $p->label, 'page' => 1]) }}"
							class="rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700">
							Detail
						</a>
					</div>
				@endforeach
			</div>
		</div>

	</div>

	{{-- Filter --}}
	<div class="mt-5 rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm">
		<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
			<div>
				<h2 class="text-lg font-extrabold text-gray-900">Monitoring Penilaian</h2>
				<p class="text-sm text-gray-500">Pantau progress & nilai akhir (periode bulanan)</p>
			</div>

			{{-- Filter Periode + Search --}}
			<form method="GET" class="flex flex-col gap-2 sm:flex-row sm:items-center">
				<input type="month" name="periode" value="{{ $periode }}"
					class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-200">

				<div class="relative">
					<input type="text" name="q" value="{{ $search }}" placeholder="Cari NIK / Nama..."
						class="w-full rounded-xl border border-gray-200 px-3 py-2 pr-10 text-sm focus:border-indigo-500 focus:ring-indigo-200">
					<span class="absolute right-3 top-2.5 text-gray-400">🔍</span>
				</div>

				<button class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
					Filter
				</button>
			</form>

			{{-- Export --}}
			<a
				href="{{ route('admin.monitoring.export') }}?periode={{ $periode }}&q={{ $search }}&kategori={{ request('kategori') }}"
				class="rounded-xl bg-green-600 bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 hover:bg-indigo-700">
				⬇ Export Excel
			</a>

		</div>
	</div>

	{{-- Table --}}
	<div class="text-md mt-5 overflow-hidden rounded-3xl border border-gray-200/70 bg-white shadow-sm">
		<table id="tblMonitoringg" class="w-full text-sm">
			<thead class="bg-gray-50 text-gray-600">
				<tr>
					<th class="px-5 py-2 text-left font-bold">NIK</th>
					<th class="px-5 py-2 text-left font-bold">Nama</th>
					<th class="px-5 py-2 text-left font-bold">Jabatan</th>
					<th class="px-5 py-2 text-left font-bold">Progress</th>
					<th class="px-5 py-2 text-right font-bold">Avg Score</th>
					<th class="px-5 py-2 text-center font-bold">Aksi</th>
				</tr>
			</thead>

			<tbody class="divide-y divide-gray-100">
				@forelse ($rows as $r)
					<tr class="hover:bg-gray-50">
						<td class="px-5 py-2 text-xs font-semibold text-gray-700">
							{{ $r->nik_relasi }}
						</td>

						<td class="px-5 py-2">
							<p class="font-bold text-gray-900">
								{{ $r->nama_karyawan ?? '(Tidak ditemukan di master)' }}
							</p>
						</td>

						<td class="px-5 py-2 text-xs text-gray-600">
							{{ $r->jabatan ?? '-' }}
						</td>

						<td class="px-5 py-2">
							<div class="flex flex-col gap-1">
								<div class="h-2 w-full rounded-full bg-gray-100">
									<div class="h-2 rounded-full bg-indigo-600" style="width: {{ $r->progress_percent }}%"></div>
								</div>
								<div class="text-xs text-gray-500">
									{{ $r->total_done }}/{{ $r->total_expected }} penilai
									<span class="ml-2 font-semibold text-gray-700">
										({{ $r->progress_percent }}%)
									</span>
								</div>
							</div>
						</td>

						<td class="px-5 py-2 text-right font-extrabold text-indigo-700">
							{{ $r->avg_score }}
						</td>

						<td class="px-5 py-2 text-center">
							<a href="{{ route('admin.monitoring.detail', $r->nik_relasi) }}?periode={{ $periode }}"
								class="rounded-xl bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-100">
								Detail →
							</a>
						</td>
					</tr>
				@empty
					<tr>
						<td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500">
							Data monitoring belum tersedia. Coba ubah periode / pencarian.
						</td>
					</tr>
				@endforelse
			</tbody>
		</table>
	</div>

	<div class="mt-6">
		{{ $rows->links() }}
	</div>

	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

	<script>
		document.addEventListener("DOMContentLoaded", function() {
			const ctx = document.getElementById('pieKategoriChart');

			const labels = @json($pieLabels);
			const values = @json($pieValues);
			const total = values.reduce((a, b) => a + b, 0);

			new Chart(ctx, {
				type: 'pie',
				data: {
					labels: labels,
					datasets: [{
						data: values,
					}]
				},
				options: {
					responsive: true,
					plugins: {
						legend: {
							position: 'bottom',
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
								size: 8
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
		});
	</script>

	<script>
		$(function() {
			$("#tblMonitoring").DataTable({
				responsive: true,
				lengthChange: true,
				autoWidth: false,

				dom: "Bfrtip",
				buttons: [
					"copy",
					"csv",
					"excel",
					"pdf",
					"print",
					"colvis"
				]
			}).buttons().container().appendTo('#tblMonitoring_wrapper .col-md-6:eq(0)');
		});
	</script>

@endsection
