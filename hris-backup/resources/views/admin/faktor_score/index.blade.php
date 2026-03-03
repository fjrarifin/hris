@extends('layouts.app')

@section('title', 'Deskripsi Score Faktor')
@section('page_title', 'Deskripsi Score')
@section('page_desc', 'Kelola deskripsi score 1-5 untuk tiap faktor')

@section('content')
	<div class="space-y-5">

		<div class="rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm">
			<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
				<div>
					<h2 class="text-lg font-extrabold text-gray-900">Kelola Deskripsi Score</h2>
					<p class="text-sm text-gray-500">Klik faktor untuk edit deskripsi score 1 - 5</p>
				</div>

				<form method="GET" class="flex items-center gap-2">
					<input type="text" name="q" value="{{ $q }}" placeholder="Cari kode / nama faktor..."
						class="w-full rounded-xl border border-gray-200 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 md:w-72">
					<button type="submit"
						class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
						Cari
					</button>
				</form>
			</div>
		</div>

		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			@foreach ($faktors as $f)
				<div class="rounded-3xl border border-gray-200/70 bg-white p-5 shadow-sm">
					<div class="flex items-start justify-between gap-3">
						<div class="min-w-0">
							<p class="text-xs font-bold text-gray-500">{{ $f->kode }}</p>
							<h3 class="truncate text-base font-extrabold text-gray-900">{{ $f->nama_faktor }}</h3>
							<p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ $f->deskripsi }}</p>
						</div>

						<a href="{{ route('admin.faktor-score.edit', $f->id) }}"
							class="shrink-0 rounded-xl bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-100">
							Edit
						</a>
					</div>
				</div>
			@endforeach
		</div>

		<div>
			{{ $faktors->links() }}
		</div>

	</div>
@endsection
