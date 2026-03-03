@extends('layouts.app')

@section('title', 'GA Dashboard')
@section('page_title', 'GA Dashboard')
@section('page_desc', 'Ringkasan performa General Affair')

@section('content')
	<div class="grid grid-cols-1 gap-6 lg:grid-cols-5">

		@foreach ([['SLA Performance', '97%', 'bg-green-50 text-green-700'], ['Downtime', '12 jam', 'bg-red-50 text-red-700'], ['Budget Terpakai', '82%', 'bg-yellow-50 text-yellow-700'], ['Insiden', '3', 'bg-red-50 text-red-700'], ['Compliance', '96%', 'bg-green-50 text-green-700']] as $card)
			<div class="{{ $card[2] }} rounded-3xl border p-5 shadow-sm">
				<p class="text-sm font-medium">{{ $card[0] }}</p>
				<p class="mt-2 text-2xl font-extrabold">{{ $card[1] }}</p>
			</div>
		@endforeach

	</div>

	<div class="mt-6 rounded-3xl border bg-white p-8 shadow-sm">
		<h2 class="text-lg font-bold">📊 Ringkasan Bulanan</h2>
		<p class="mt-2 text-gray-600">
			Data berikut merupakan ringkasan performa GA periode berjalan.
		</p>
	</div>
@endsection
