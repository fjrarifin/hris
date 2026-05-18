@extends('layouts.app')

@section('title', 'Template Email Slip Gaji')
@section('page-title', 'Template Email Slip Gaji')

@section('content')
	@if (session('success'))
		<div class="mb-3 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700">
			{{ session('success') }}
		</div>
	@endif

	<div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
		<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
			<div>
				<h2 class="mb-0 text-base font-bold text-slate-900">Template Email Slip Gaji</h2>
				<p class="mb-0 text-xs text-slate-500">Placeholder: {nama_karyawan}, {nik}, {periode}, {total_dibayarkan}</p>
			</div>
			<a href="{{ route('hr.payroll.index') }}" class="btn btn-secondary btn-sm font-bold">
				<i class="fas fa-arrow-left mr-1"></i> Kembali
			</a>
		</div>

		<form method="POST" action="{{ route('hr.payroll.email-template.update') }}">
			@csrf
			<div class="mb-3">
				<label class="mb-1 block text-xs font-bold text-slate-600">Subject</label>
				<input type="text" name="subject" value="{{ old('subject', $template->subject) }}"
					class="form-control @error('subject') is-invalid @enderror">
				@error('subject')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>

			<div class="mb-3">
				<label class="mb-1 block text-xs font-bold text-slate-600">Body</label>
				<textarea name="body" rows="12" class="form-control @error('body') is-invalid @enderror">{{ old('body', $template->body) }}</textarea>
				@error('body')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>

			<div class="mb-4">
				<label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
					<input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active))>
					Aktif
				</label>
			</div>

			<button type="submit" class="btn btn-primary btn-sm font-bold">
				<i class="fas fa-save mr-1"></i> Simpan Template
			</button>
		</form>
	</div>
@endsection
