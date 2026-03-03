@extends('layouts.app')

@section('title', 'Vendor Management')
@section('page_title', 'Vendor Management')
@section('page_desc', 'Evaluasi & scoring vendor')

@section('content')
	<div class="rounded-3xl border bg-white p-8 shadow-sm">
		<h2 class="text-lg font-bold">🤝 Vendor Score</h2>

		<div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
			<div class="rounded-xl bg-green-50 p-4">
				<p class="font-semibold">Cleaning Service</p>
				<p class="text-sm text-gray-600">Score: 92 (A)</p>
			</div>
		</div>
	</div>
@endsection
