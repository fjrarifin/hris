@extends('layouts.app')

@section('title', 'Asset & Fasilitas')
@section('page_title', 'Asset & Fasilitas')
@section('page_desc', 'Manajemen aset & fasilitas')

@section('content')
	<div class="rounded-3xl border bg-white p-8 shadow-sm">
		<h2 class="text-lg font-bold">🏢 Daftar Asset</h2>

		<ul class="mt-4 space-y-3 text-sm">
			<li class="flex justify-between">
				<span>Lift Gedung A</span>
				<span class="text-green-600">Baik</span>
			</li>
			<li class="flex justify-between">
				<span>AC Gedung B</span>
				<span class="text-yellow-600">Perlu Maintenance</span>
			</li>
		</ul>
	</div>
@endsection
