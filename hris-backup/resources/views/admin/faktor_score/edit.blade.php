@extends('layouts.app')

@section('title', 'Edit Score Faktor')
@section('page_title', 'Edit Score Faktor')
@section('page_desc', 'Atur deskripsi score 1 - 5')

@section('content')
	<div class="space-y-6">

		@php

			$levelLabel = [
			    'Sr. Manager' => 'Manager',
			    'Md. Manager' => 'Manager',
			    'Jr. Manager' => 'Manager',

			    'Sr. Asst. Manager' => 'Asst. Manager',
			    'Jr. Asst. Manager' => 'Asst. Manager',

			    'Sr. Supervisor' => 'Supervisor',
			    'Jr. Supervisor' => 'Supervisor',

			    'Sr. Staff' => 'Staff',
			    'Jr. Staff' => 'Staff',

			    'Sr. Operator' => 'Operator',
			    'Md. Operator' => 'Operator',
			    'Jr. Operator' => 'Operator',
			];
		@endphp

		<div class="rounded-3xl border bg-white p-6 shadow-sm">
			<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
				<div>
					<h2 class="text-xl font-extrabold">{{ $faktor->nama_faktor }}</h2>
					<p class="text-sm text-gray-500">
						{{ $levelLabel[$level->nama_level] ?? $level->nama_level }}
					</p>
				</div>

				<a href="{{ route('admin.faktor-score.level', [$level->id]) }}"
					class="inline-flex items-center gap-2 rounded-2xl bg-gray-100 px-5 py-2 text-sm font-bold text-gray-700 transition hover:bg-gray-200">
					← Kembali
				</a>
			</div>
		</div>

		<form method="POST" action="{{ route('admin.faktor-score.update', [$level->id, $faktor->id]) }}" class="space-y-4">
			@csrf

			@foreach ($scores as $sc)
				<div class="rounded-3xl border bg-white p-5 shadow-sm">
					<p class="text-sm font-bold text-gray-900">Score {{ $sc->score }}</p>
					<textarea name="deskripsi[{{ $sc->score }}]" rows="3"
					 class="mt-2 w-full rounded-2xl border border-gray-200 p-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $sc->deskripsi }}</textarea>
				</div>
			@endforeach

			<div class="flex justify-end">
				<button class="rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white hover:bg-indigo-700">
					Simpan ✅
				</button>
			</div>
		</form>

	</div>
@endsection
