@extends('layouts.app')

@section('title', 'Budget & Cost')
@section('page_title', 'Budget & Cost')
@section('page_desc', 'Monitoring budget GA')

@section('content')
	<div class="rounded-3xl border bg-white p-8 shadow-sm">
		<h2 class="text-lg font-bold">💰 Budget Tahunan</h2>

		<ul class="mt-4 text-sm text-gray-700">
			<li>Total: Rp 2.000.000.000</li>
			<li>Terpakai: Rp 1.640.000.000</li>
			<li>Sisa: Rp 360.000.000</li>
		</ul>
	</div>
@endsection
