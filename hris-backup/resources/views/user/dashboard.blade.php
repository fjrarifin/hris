@extends('layouts.app')

@section('title', 'User Dashboard')
@section('page_title', 'User Dashboard')
@section('page_desc', 'Ringkasan informasi akun kamu')

@section('content')
	<div class="rounded-3xl border bg-white p-8 shadow-sm">
		<h2 class="text-xl font-extrabold text-gray-900">Informasi</h2>

		<p class="mt-3 text-sm leading-relaxed text-gray-600">
			Aplikasi ini masih dalam tahap pengembangan awal. Fitur-fitur utama seperti manajemen karyawan, relasi, dan laporan
			akan segera ditambahkan.
			Nantikan pembaruan selanjutnya!.
		</p>

		<p class="mt-4 text-sm leading-relaxed text-gray-600">
			Untuk saat ini, bisa langsung ke menu <b>"Penilaian"</b> di sidebar untuk melihat fitur penilaian karyawan.
		</p>

		{{-- ✅ CTA button --}}
		<div class="mt-6">
			<a href="{{ route('penilaian.index') }}"
				class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-indigo-700">
				✍️ Mulai Penilaian
			</a>
		</div>
	</div>
@endsection
