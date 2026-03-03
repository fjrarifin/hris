@extends('layouts.app')

@section('title', 'GA Dashboard')
@section('page-title', 'GA Dashboard')

@section('content')
	<div class="row">

		{{-- KPI CARDS --}}
		@foreach ([['SLA (%)', $kpi['sla'], 'success'], ['Downtime (jam)', $kpi['downtime'], 'danger'], ['Budget Terpakai (%)', $kpi['budget_usage'], 'warning'], ['Insiden', $kpi['incident'], 'danger'], ['Compliance (%)', $kpi['compliance'], 'success']] as $card)
			<div class="col-md-2">
				<div class="card card-outline card-{{ $card[2] }} rounded-xl text-center">
					<div class="card-body">
						<h6>{{ $card[0] }}</h6>
						<h3 class="font-weight-bold">{{ $card[1] }}</h3>
					</div>
				</div>
			</div>
		@endforeach

	</div>

	{{-- CHART --}}
	<div class="card card-primary card-outline rounded-xl">
		<div class="card-header">
			<h3 class="card-title">SLA Performance (6 Bulan)</h3>
		</div>
		<div class="card-body">
			<canvas id="slaChart"></canvas>
		</div>
	</div>
@endsection

@push('scripts')
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script>
		const ctx = document.getElementById('slaChart');
		new Chart(ctx, {
			type: 'line',
			data: {
				labels: @json($slaTrend['labels']),
				datasets: [{
					label: 'SLA %',
					data: @json($slaTrend['data']),
					borderWidth: 3,
					borderColor: '#2563eb',
					fill: false,
					tension: 0.4
				}]
			}
		});
	</script>
@endpush
