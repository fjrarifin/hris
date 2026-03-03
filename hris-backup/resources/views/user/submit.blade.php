@extends('layouts.app')

@section('title', 'Penilaian Karyawan')
@section('page_title', 'Penilaian Karyawan')
@section('page_desc', 'Penilaian karyawan melalui sistem HRIS')

@section('content')
	<div class="mx-auto mt-2 max-w-3xl">
		<div class="rounded-3xl border border-gray-200/70 bg-white p-6 text-center shadow-sm">
			<div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-green-50 text-2xl">
				✅
			</div>

			<h1 class="mt-4 text-2xl font-extrabold text-gray-900">
				Penilaian Periode Ini Sudah Terkirim
			</h1>

			<p class="mt-2 text-sm leading-relaxed text-gray-600">
				Anda sudah melakukan submit penilaian untuk periode:
				<span class="font-bold text-gray-900">{{ $periode }}</span>
			</p>

			@if (!empty($tanggal_submit))
				<p class="mt-1 text-xs text-gray-500">
					Terkirim pada: {{ \Carbon\Carbon::parse($tanggal_submit)->format('d M Y H:i') }}
				</p>
			@endif

			<div class="mt-4 rounded-2xl border border-gray-200/70 bg-gray-50 p-4 text-left">
				<p class="mb-1 text-sm font-semibold text-gray-800">Catatan:</p>
				<p class="text-sm leading-relaxed text-gray-600">
					Sesuai ketentuan, penilaian yang sudah dikirim <b>tidak dapat dilihat kembali</b>
					dan <b>tidak dapat diisi ulang</b> pada periode yang sama.
				</p>
			</div>

			<div class="mt-5 flex items-center justify-center gap-2">
				<a href="{{ route('user.dashboard') }}"
					class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700">
					Kembali ke Dashboard
				</a>
			</div>
		</div>
	</div>
@endsection
