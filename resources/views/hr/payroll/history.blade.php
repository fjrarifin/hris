@extends('layouts.app')

@section('title', 'History Payroll')
@section('page-title', 'History Payroll')

@section('content')
	<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
		<div>
			<h2 class="mb-0 text-base font-bold text-slate-900">History Payroll per Periode</h2>
			<p class="mb-0 text-xs text-slate-500">Ringkasan jumlah payroll, approval, lock, dan total dibayarkan.</p>
		</div>
		<a href="{{ route('hr.payroll.index') }}" class="btn btn-secondary btn-sm font-bold">
			<i class="fas fa-arrow-left mr-1"></i> Kembali
		</a>
	</div>

	<div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
		<table id="tblPayrollHistory" class="table-bordered table-striped table-hover table w-full text-xs">
			<thead class="bg-slate-50 text-slate-600">
				<tr>
					<th class="text-center">Periode</th>
					<th class="text-center">Total Payroll</th>
					<th class="text-center">Approved</th>
					<th class="text-center">Locked</th>
					<th class="text-right">Total Dibayarkan</th>
					<th class="text-center">Aksi</th>
				</tr>
			</thead>
			<tbody>
				@foreach ($periods as $period)
					<tr>
						<td class="text-center font-bold">
							{{ \Carbon\Carbon::parse($period->periode_start)->format('d M Y') }}
							-
							{{ \Carbon\Carbon::parse($period->periode_end)->format('d M Y') }}
						</td>
						<td class="text-center">{{ $period->total_payroll }}</td>
						<td class="text-center">{{ $period->total_approved }}</td>
						<td class="text-center">{{ $period->total_locked }}</td>
						<td class="text-right font-bold">Rp {{ number_format($period->total_dibayarkan, 0, ',', '.') }}</td>
						<td class="text-center">
							<a class="btn btn-primary btn-xs font-bold"
								href="{{ route('hr.payroll.index', ['periode_start' => $period->periode_start, 'periode_end' => $period->periode_end]) }}">
								<i class="fas fa-eye mr-1"></i> Detail
							</a>
							<a class="btn btn-success btn-xs font-bold"
								href="{{ route('hr.payroll.export', ['periode_start' => $period->periode_start, 'periode_end' => $period->periode_end]) }}">
								<i class="fas fa-file-excel mr-1"></i> Export
							</a>
						</td>
					</tr>
				@endforeach
			</tbody>
		</table>
	</div>
@endsection

@push('scripts')
	<script>
		$(document).ready(function() {
			$('#tblPayrollHistory').DataTable({
				responsive: true,
				autoWidth: false,
				pageLength: 10,
				sort: false
			});
		});
	</script>
@endpush
