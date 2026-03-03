@extends('layouts.app')

@section('title', 'Kelola Deskripsi Score')
@section('page_title', 'Kelola Deskripsi Score')
@section('page_desc', 'Edit faktor + score 1 - 5 untuk level ini')

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
					<h2 class="text-xl font-extrabold">{{ $levelLabel[$level->nama_level] ?? $level->nama_level }}</h2>
					<p class="text-sm text-gray-500">
						Total indikator: {{ $indikatorHave }}/{{ $indikatorNeed }}
					</p>
				</div>

				<a href="{{ route('admin.faktor-score.index') }}"
					class="inline-flex items-center gap-2 rounded-2xl bg-gray-100 px-5 py-2 text-sm font-bold text-gray-700 transition hover:bg-gray-200">
					← Kembali
				</a>
			</div>
		</div>

		<div class="grid gap-5 md:grid-cols-2">
			@foreach ($templates as $t)
				<div class="rounded-3xl border bg-white p-6 shadow-sm">
					<div class="flex items-start justify-between gap-3">
						<div>
							<p class="text-lg font-extrabold">{{ $t->nama_faktor }}</p>
							<p class="mt-2 line-clamp-2 text-sm text-gray-500">{{ $t->deskripsi }}</p>
						</div>

						<a href="{{ route('admin.faktor-score.edit', [$templateLevelId, $t->faktor_id]) }}"
							class="rounded-2xl bg-indigo-50 px-4 py-2 text-sm font-bold text-indigo-700 hover:bg-indigo-100">
							Edit
						</a>
					</div>
				</div>
			@endforeach
		</div>

	</div>
@endsection
