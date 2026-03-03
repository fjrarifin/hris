@extends('layouts.app')

@section('title', 'Maintenance & Downtime')
@section('page_title', 'Maintenance & Downtime')
@section('page_desc', 'Monitoring downtime fasilitas')

@section('content')
	<div class="rounded-3xl border bg-white p-8 shadow-sm">
		<h2 class="text-lg font-bold">🛠 Downtime Terakhir</h2>

		<div class="mt-4 space-y-2 text-sm text-gray-700">
			<p>• AC Gedung B — 2 jam</p>
			<p>• Lift Gedung A — 1 jam</p>
		</div>
	</div>
@endsection
