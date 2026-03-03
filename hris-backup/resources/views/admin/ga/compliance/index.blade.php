@extends('layouts.app')

@section('title', 'Compliance')
@section('page_title', 'Compliance')
@section('page_desc', 'Status perizinan & compliance')

@section('content')
	<div class="rounded-3xl border bg-white p-8 shadow-sm">
		<h2 class="text-lg font-bold">📄 Compliance</h2>

		<ul class="mt-4 text-sm text-gray-700">
			<li>IMB — Aktif</li>
			<li>SLF — Aktif</li>
			<li>APAR — Expired 2 bulan lagi</li>
		</ul>
	</div>
@endsection
