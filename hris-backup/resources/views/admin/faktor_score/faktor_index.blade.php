@extends('layouts.app')

@section('title', 'Deskripsi Score')
@section('page_title', 'Deskripsi Score')
@section('page_desc', 'Kelola faktor & score untuk level: ' . $level->nama_level)

@section('content')

	<div class="space-y-5">

		{{-- HEADER --}}
		<div class="rounded-3xl border bg-white p-6 shadow-sm">
			<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">

				<div>
					<p class="text-xs font-bold uppercase tracking-wider text-gray-400">Level</p>
					<h2 class="mt-1 text-xl font-extrabold text-gray-900">{{ $level->nama_level }}</h2>

					<a href="{{ route('admin.faktor-score.index') }}"
						class="mt-2 inline-flex text-sm font-semibold text-indigo-600 hover:underline">
						← Kembali pilih level
					</a>
				</div>

				<form method="GET" class="flex w-full gap-2 md:w-[420px]">
					<input type="text" name="q" value="{{ $q }}"
						class="w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
						placeholder="Cari kode / nama faktor...">
					<button type="submit"
						class="rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
						Cari
					</button>
				</form>

			</div>
		</div>

		<form method="POST" action="{{ route('admin.faktor-score.generate', $level->id) }}">
			@csrf
			<button type="submit"
				class="rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
				⚡ Auto Generate Default Score
			</button>
		</form>

		{{-- LIST FAKTOR --}}
		<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
			@forelse($faktor as $f)
				<div class="rounded-3xl border bg-white p-5 shadow-sm">

					<div class="flex items-start justify-between gap-3">
						<div class="min-w-0">
							<p class="text-xs font-bold text-gray-400">{{ $f->kode }}</p>
							<h3 class="mt-1 truncate text-base font-extrabold text-gray-900">
								{{ $f->nama_faktor }}
							</h3>

							<p class="mt-2 line-clamp-2 text-xs text-gray-500">
								{{ $f->deskripsi }}
							</p>
						</div>

						<a href="{{ route('admin.faktor-score.edit', $f->id) }}"
							class="rounded-xl bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-100">
							Edit
						</a>
					</div>



					<div class="mt-4 rounded-2xl bg-gray-50 p-4">
						<p class="text-xs font-semibold text-gray-600">
							Score 1 - 5
							<span
								class="{{ $f->is_complete ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }} ml-2 rounded-full px-2 py-0.5 text-[11px] font-bold">
								{{ $f->filled_score }}/5
							</span>
						</p>
					</div>

				</div>
			@empty
				<div class="rounded-3xl border bg-white p-8 text-center shadow-sm md:col-span-2 xl:col-span-3">
					<p class="text-sm font-semibold text-gray-700">Tidak ada faktor untuk level ini.</p>
				</div>
			@endforelse
		</div>

	</div>

@endsection
