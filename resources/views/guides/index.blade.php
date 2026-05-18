@extends('layouts.app')

@section('title', 'Panduan Aplikasi')
@section('page-title', 'Panduan Aplikasi')

@section('content')
	@php
		$userLevel = (int) auth()->user()->level;
	@endphp

	<style>
		.guide-card {
			border: 1px solid #e5e7eb;
			border-top: 3px solid #3b82f6;
			border-radius: 1.5rem;
			background: #fff;
			box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
		}

		.guide-step {
			position: relative;
			border: 1px solid #e5e7eb;
			border-radius: 18px;
			background: #fff;
			padding: 16px;
			min-height: 128px;
		}

		.guide-step-number {
			display: inline-flex;
			width: 32px;
			height: 32px;
			align-items: center;
			justify-content: center;
			border-radius: 999px;
			background: #2563eb;
			color: #fff;
			font-weight: 800;
			font-size: 13px;
		}

		.guide-pill {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			border-radius: 999px;
			padding: 5px 10px;
			font-size: 11px;
			font-weight: 800;
			white-space: nowrap;
		}
	</style>

	<div class="guide-card mb-4 p-4 md:p-5">
		<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
			<div>
				<div class="mb-2 text-xs font-bold uppercase tracking-wide text-blue-600">Employee Self Service Guide</div>
				<h2 class="mb-1 text-2xl font-extrabold text-slate-900">Panduan Cuti, Public Holiday, dan Profil</h2>
				<p class="mb-0 text-sm text-slate-600">
					Gunakan halaman ini sebagai acuan alur pengajuan, approval atasan, approval HR, dan pengecekan status akhir.
				</p>
			</div>
			<div class="flex flex-wrap gap-2">
				@if ($userLevel === 3 && Route::has('staff.leave.index'))
					<a href="{{ route('staff.leave.index') }}" class="btn btn-primary btn-sm rounded-pill font-bold">
						<i class="fas fa-calendar-check mr-1"></i> Pengajuan Cuti
					</a>
				@endif
				@if ($userLevel === 3 && Route::has('staff.public-holiday.index'))
					<a href="{{ route('staff.public-holiday.index') }}" class="btn btn-outline-primary btn-sm rounded-pill font-bold">
						<i class="fas fa-calendar-day mr-1"></i> Public Holiday
					</a>
				@endif
				@if ($userLevel === 3 && Route::has('staff.profile.index'))
					<a href="{{ route('staff.profile.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill font-bold">
						<i class="fas fa-user-cog mr-1"></i> Profil
					</a>
				@endif
			</div>
		</div>
	</div>

	<div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
		<a href="#panduan-cuti" class="guide-card p-4 text-decoration-none">
			<div class="mb-2 flex items-center justify-between">
				<span class="guide-pill bg-blue-50 text-blue-700"><i class="fas fa-calendar-check"></i> Cuti</span>
				<i class="fas fa-arrow-right text-slate-400"></i>
			</div>
			<div class="font-extrabold text-slate-900">Pengajuan Cuti</div>
			<div class="text-sm text-slate-600">Ajukan tanggal cuti, pantau approval, dan cek saldo cuti.</div>
		</a>
		<a href="#panduan-ph" class="guide-card p-4 text-decoration-none">
			<div class="mb-2 flex items-center justify-between">
				<span class="guide-pill bg-cyan-50 text-cyan-700"><i class="fas fa-calendar-day"></i> PH</span>
				<i class="fas fa-arrow-right text-slate-400"></i>
			</div>
			<div class="font-extrabold text-slate-900">Public Holiday</div>
			<div class="text-sm text-slate-600">Claim hari libur yang tersedia dan ikuti approval sampai final HR.</div>
		</a>
		<a href="#panduan-profil" class="guide-card p-4 text-decoration-none">
			<div class="mb-2 flex items-center justify-between">
				<span class="guide-pill bg-slate-100 text-slate-700"><i class="fas fa-id-card"></i> Profil</span>
				<i class="fas fa-arrow-right text-slate-400"></i>
			</div>
			<div class="font-extrabold text-slate-900">Data Profil</div>
			<div class="text-sm text-slate-600">Pastikan nomor HP, email, foto, dan password selalu valid.</div>
		</a>
	</div>

	<div id="panduan-cuti" class="guide-card mb-4 p-4 md:p-5">
		<div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
			<div>
				<h3 class="mb-1 text-xl font-extrabold text-slate-900">Flow Pengajuan Cuti</h3>
				<p class="mb-0 text-sm text-slate-600">Alur ini berlaku untuk cuti tahunan dan cuti lainnya dari halaman staff.</p>
			</div>
			<span class="guide-pill bg-blue-50 text-blue-700">Maksimal 5 hari per pengajuan</span>
		</div>

		<div class="grid grid-cols-1 gap-3 md:grid-cols-5">
			<div class="guide-step">
				<span class="guide-step-number">1</span>
				<div class="mt-3 font-bold text-slate-900">Staff Mengajukan</div>
				<p class="mb-0 mt-1 text-xs text-slate-600">Buka Pengajuan Cuti, cek saldo tersedia, klik Ajukan Cuti, isi jenis, tanggal, dan alasan.</p>
			</div>
			<div class="guide-step">
				<span class="guide-step-number">2</span>
				<div class="mt-3 font-bold text-slate-900">Validasi Sistem</div>
				<p class="mb-0 mt-1 text-xs text-slate-600">Tanggal tidak boleh lewat, durasi maksimal 5 hari, dan tidak boleh bentrok dengan cuti atau PH lain.</p>
			</div>
			<div class="guide-step">
				<span class="guide-step-number">3</span>
				<div class="mt-3 font-bold text-slate-900">Approval Atasan</div>
				<p class="mb-0 mt-1 text-xs text-slate-600">Atasan menerima notifikasi dan bisa approve/reject via link approval atau menu Approval Cuti / PH.</p>
			</div>
			<div class="guide-step">
				<span class="guide-step-number">4</span>
				<div class="mt-3 font-bold text-slate-900">Approval HR</div>
				<p class="mb-0 mt-1 text-xs text-slate-600">Setelah atasan approve, HR melakukan pengecekan akhir dan approve/reject dari halaman HR Approval.</p>
			</div>
			<div class="guide-step">
				<span class="guide-step-number">5</span>
				<div class="mt-3 font-bold text-slate-900">Final & Saldo</div>
				<p class="mb-0 mt-1 text-xs text-slate-600">Jika HR approve, status final menjadi Disetujui HR dan pemakaian menjadi dasar pengurangan saldo cuti.</p>
			</div>
		</div>

		<div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
			<div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
				<div class="mb-2 font-bold text-slate-900">Yang perlu diperhatikan staff</div>
				<ul class="mb-0 pl-4 text-sm text-slate-700">
					<li>Saldo cuti tersedia berasal dari accrual cuti yang belum digunakan dan belum expired.</li>
					<li>Pengajuan yang masih pending bisa dihapus oleh pemohon.</li>
					<li>Jika ditolak, alasan penolakan tampil di riwayat pengajuan.</li>
				</ul>
			</div>
			<div class="rounded-2xl border border-slate-200 bg-white p-4">
				<div class="mb-2 font-bold text-slate-900">Status yang akan terlihat</div>
				<div class="flex flex-wrap gap-2">
					<span class="badge badge-warning">Menunggu</span>
					<span class="badge badge-info">Disetujui Atasan</span>
					<span class="badge badge-success">Disetujui HR</span>
					<span class="badge badge-danger">Ditolak</span>
					<span class="badge badge-secondary">Dibatalkan</span>
				</div>
			</div>
		</div>
	</div>

	<div id="panduan-ph" class="guide-card mb-4 p-4 md:p-5">
		<div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
			<div>
				<h3 class="mb-1 text-xl font-extrabold text-slate-900">Flow Pengajuan Public Holiday</h3>
				<p class="mb-0 text-sm text-slate-600">PH digunakan untuk claim hari libur yang masih aktif dan masih dalam masa berlaku.</p>
			</div>
			<span class="guide-pill bg-cyan-50 text-cyan-700">Masa claim maksimal 90 hari</span>
		</div>

		<div class="grid grid-cols-1 gap-3 md:grid-cols-5">
			<div class="guide-step">
				<span class="guide-step-number">1</span>
				<div class="mt-3 font-bold text-slate-900">Pilih PH</div>
				<p class="mb-0 mt-1 text-xs text-slate-600">Buka Public Holiday, pilih hari libur yang tersedia, lalu tentukan tanggal pengambilan.</p>
			</div>
			<div class="guide-step">
				<span class="guide-step-number">2</span>
				<div class="mt-3 font-bold text-slate-900">Validasi Tanggal</div>
				<p class="mb-0 mt-1 text-xs text-slate-600">Claim tidak boleh tanggal lampau, sebelum tanggal PH, lewat 90 hari, double claim, atau bentrok cuti.</p>
			</div>
			<div class="guide-step">
				<span class="guide-step-number">3</span>
				<div class="mt-3 font-bold text-slate-900">Approval Atasan</div>
				<p class="mb-0 mt-1 text-xs text-slate-600">Atasan approve/reject melalui link approval atau menu Approval Cuti / PH di aplikasi.</p>
			</div>
			<div class="guide-step">
				<span class="guide-step-number">4</span>
				<div class="mt-3 font-bold text-slate-900">Approval HR</div>
				<p class="mb-0 mt-1 text-xs text-slate-600">HR memvalidasi pengajuan yang sudah disetujui atasan sebelum status menjadi final.</p>
			</div>
			<div class="guide-step">
				<span class="guide-step-number">5</span>
				<div class="mt-3 font-bold text-slate-900">Saldo PH</div>
				<p class="mb-0 mt-1 text-xs text-slate-600">PH yang sudah disetujui atasan tidak muncul lagi di daftar PH tersedia, lalu HR approval menjadi finalisasi.</p>
			</div>
		</div>
	</div>

	<div class="guide-card mb-4 p-4 md:p-5">
		<h3 class="mb-3 text-xl font-extrabold text-slate-900">Cara Approval Untuk Atasan dan HR</h3>
		<div class="grid grid-cols-1 gap-3 md:grid-cols-3">
			<div class="rounded-2xl border border-slate-200 p-4">
				<div class="mb-2 font-bold text-slate-900"><i class="fas fa-link mr-1 text-blue-500"></i> Via Link Approval</div>
				<p class="mb-0 text-sm text-slate-600">Atasan menerima link approval dari notifikasi. Link hanya bisa dipakai selama masa berlaku token, yaitu 24 jam sejak pengajuan dibuat.</p>
			</div>
			<div class="rounded-2xl border border-slate-200 p-4">
				<div class="mb-2 font-bold text-slate-900"><i class="fas fa-check-circle mr-1 text-green-500"></i> Via Aplikasi</div>
				<p class="mb-0 text-sm text-slate-600">Jika memiliki bawahan, atasan melihat menu Approval Cuti / PH. Dari sana atasan bisa approve atau reject pengajuan pending.</p>
			</div>
			<div class="rounded-2xl border border-slate-200 p-4">
				<div class="mb-2 font-bold text-slate-900"><i class="fas fa-user-shield mr-1 text-slate-600"></i> Final HR</div>
				<p class="mb-0 text-sm text-slate-600">HR hanya memproses pengajuan yang sudah disetujui atasan. Keputusan HR adalah status akhir yang terlihat oleh staff.</p>
			</div>
		</div>
		@if ($userLevel === 2 && Route::has('hr.approval.index'))
			<div class="mt-3 flex flex-wrap gap-2">
				<a href="{{ route('hr.approval.index', 'leave') }}" class="btn btn-outline-primary btn-sm rounded-pill font-bold">
					<i class="fas fa-calendar-check mr-1"></i> HR Approval Cuti
				</a>
				<a href="{{ route('hr.approval.index', 'ph') }}" class="btn btn-outline-primary btn-sm rounded-pill font-bold">
					<i class="fas fa-calendar-day mr-1"></i> HR Approval PH
				</a>
			</div>
		@endif
	</div>

	<div id="panduan-profil" class="guide-card p-4 md:p-5">
		<div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
			<div>
				<h3 class="mb-1 text-xl font-extrabold text-slate-900">Panduan Pengisian Data Profil</h3>
				<p class="mb-0 text-sm text-slate-600">Data profil yang benar membantu notifikasi, approval, dan komunikasi HR berjalan lancar.</p>
			</div>
			@if ($userLevel === 3 && Route::has('staff.profile.index'))
				<a href="{{ route('staff.profile.index') }}" class="btn btn-primary btn-sm rounded-pill font-bold">
					<i class="fas fa-user-edit mr-1"></i> Buka Profil
				</a>
			@endif
		</div>

		<div class="grid grid-cols-1 gap-3 md:grid-cols-3">
			<div class="rounded-2xl border border-slate-200 bg-white p-4">
				<div class="mb-2 font-bold text-slate-900">Nomor HP</div>
				<p class="mb-0 text-sm text-slate-600">Wajib diisi dan sebaiknya memakai nomor aktif. Nomor ini dipakai sebagai dasar komunikasi dan notifikasi approval.</p>
			</div>
			<div class="rounded-2xl border border-slate-200 bg-white p-4">
				<div class="mb-2 font-bold text-slate-900">Email & Foto</div>
				<p class="mb-0 text-sm text-slate-600">Email wajib valid. Foto profil opsional, format JPG/JPEG/PNG, maksimal 2 MB.</p>
			</div>
			<div class="rounded-2xl border border-slate-200 bg-white p-4">
				<div class="mb-2 font-bold text-slate-900">Password</div>
				<p class="mb-0 text-sm text-slate-600">Gunakan menu ubah password jika diminta sistem atau saat ingin mengganti password. Minimal 8 karakter dan wajib konfirmasi.</p>
			</div>
		</div>
	</div>
@endsection
