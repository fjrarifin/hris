@extends('layouts.app')

@section('title', 'Performance')
@section('page-title', 'Performance')

@section('content')
	<div class="row">
		<div class="col-12">
			<div class="card card-primary card-outline rounded-xl">
				<div class="card-body">
					<h1 class="text-lg font-extrabold text-gray-900">
						✅ Penilaian Periode Ini Sudah Terkirim
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
							Penilaian yang sudah dikirim <b>tidak dapat dilihat kembali</b>
							dan <b>tidak dapat diisi ulang</b> pada periode yang sama.
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection
