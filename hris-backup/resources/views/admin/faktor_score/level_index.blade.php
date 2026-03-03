@extends('layouts.app')

@section('title', 'Deskripsi Score')
@section('page_title', 'Deskripsi Score')
@section('page_desc', 'Pilih level untuk mengelola deskripsi score 1 - 5')

@section('content')
	<div class="space-y-6">

		<div class="rounded-3xl border bg-white p-6 shadow-sm">
			<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
				<div>
					<h2 class="text-lg font-extrabold">Kelola Deskripsi Score</h2>
					<p class="text-sm text-gray-500">Pilih level jabatan, lalu edit deskripsi score per faktor.</p>
				</div>

				<form method="GET" class="flex gap-2">
					<input type="text" name="q" value="{{ $q }}"
						class="w-64 rounded-2xl border border-gray-200 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
						placeholder="Cari level...">
					<button class="rounded-2xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white hover:bg-indigo-700">
						Cari
					</button>
				</form>
			</div>
		</div>

		<div class="grid gap-5 md:grid-cols-2">
			@foreach ($levels as $lv)
				@php
					$have = $templateCount[$lv->id] ?? 0;
					$need = (int) $lv->indikator_total;

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

				<a href="{{ route('admin.faktor-score.level', $lv->id) }}">
					<div class="rounded-3xl border bg-white p-6 shadow-sm">
						<div class="flex items-start justify-between gap-3">
							<div>
								<p class="mt-1 text-xl font-extrabold">{{ $levelLabel[$lv->nama_level] ?? $lv->nama_level }}</p>

								<div class="mt-3 flex flex-wrap gap-2">
									<span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">
										{{ $need }} indikator
									</span>

								</div>
							</div>

							<span class="rounded-2xl bg-indigo-50 px-4 py-2 text-sm font-bold text-indigo-700 hover:bg-indigo-100">
								Kelola →
							</span>
						</div>
					</div>
				</a>
			@endforeach
		</div>

	</div>
@endsection
