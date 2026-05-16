@extends('layouts.app')

@section('title', 'Dasbor')
@section('page-title', 'Dasbor')

@section('content')

	<div class="space-y-6">

		{{-- ================= SAMBUTAN ================= --}}
		<div class="rounded-2xl bg-gradient-to-r from-blue-500 to-indigo-600 p-6 text-white shadow-sm">
			<h2 class="text-2xl font-bold">Selamat Datang, {{ Auth::user()->name }}! 👋</h2>
			<p class="mt-1 text-sm text-blue-100">{{ now()->isoFormat('dddd, D MMMM YYYY') }}</p>
		</div>

		{{-- ================= RINGKASAN UTAMA ================= --}}
		<div class="grid grid-cols-1 gap-4 md:grid-cols-4">

			{{-- Kehadiran --}}
			<div class="card-outline card-primary rounded-2xl bg-white p-4 shadow-sm">
				<p class="text-xs text-gray-500">Kehadiran Hari Ini</p>
				<p class="mt-1 text-lg font-extrabold text-green-600">
					Hadir
				</p>
				<p class="mt-1 text-xs text-gray-400">
					Masuk 08:02
				</p>
			</div>

			{{-- Sisa Cuti --}}
			<div class="card-outline card-indigo rounded-2xl bg-white p-4 shadow-sm">
				<p class="text-xs text-gray-500">Sisa Cuti</p>
				<p class="mt-1 text-lg font-extrabold text-indigo-600">
					8 Hari
				</p>
				<p class="mt-1 text-xs text-gray-400">
					Dari 12 hari/tahun
				</p>
			</div>

			{{-- Pengajuan --}}
			<div class="card-outline card-yellow rounded-2xl bg-white p-4 shadow-sm">
				<p class="text-xs text-gray-500">Pengajuan</p>
				<p class="mt-1 text-lg font-extrabold text-yellow-600">
					1 Menunggu
				</p>
				<p class="mt-1 text-xs text-gray-400">
					Menunggu approval
				</p>
			</div>

			{{-- Penilaian --}}
			<div class="card-outline card-red rounded-2xl bg-white p-4 shadow-sm">
				<p class="text-xs text-gray-500">Penilaian Kinerja</p>
				<p class="mt-1 text-lg font-extrabold text-red-600">
					Belum Selesai
				</p>
				<p class="mt-1 text-xs text-gray-400">
					Periode Feb 2026
				</p>
			</div>

		</div>

		{{-- ================= STATISTIK KEHADIRAN BULAN INI ================= --}}
		<div class="card-outline card-gray rounded-2xl bg-white p-4 shadow-sm">
			<h3 class="mb-4 text-sm font-extrabold text-gray-900">
				📊 Statistik Kehadiran Bulan Ini
			</h3>

			<div class="grid grid-cols-2 gap-4 md:grid-cols-5">
				<div class="text-center">
					<p class="text-2xl font-bold text-green-600">20</p>
					<p class="text-xs text-gray-500">Hadir</p>
				</div>
				<div class="text-center">
					<p class="text-2xl font-bold text-blue-600">2</p>
					<p class="text-xs text-gray-500">Cuti</p>
				</div>
				<div class="text-center">
					<p class="text-2xl font-bold text-yellow-600">1</p>
					<p class="text-xs text-gray-500">Izin</p>
				</div>
				<div class="text-center">
					<p class="text-2xl font-bold text-red-600">0</p>
					<p class="text-xs text-gray-500">Alpa</p>
				</div>
				<div class="text-center">
					<p class="text-2xl font-bold text-purple-600">3</p>
					<p class="text-xs text-gray-500">Terlambat</p>
				</div>
			</div>
		</div>

		{{-- ================= INFORMASI & TASK ================= --}}
		<div class="grid grid-cols-1 gap-4 md:grid-cols-2">

			{{-- Informasi Terbaru --}}
			<div class="card-outline card-gray rounded-2xl bg-white p-4 shadow-sm">
				<h3 class="mb-3 text-sm font-extrabold text-gray-900">
					📢 Informasi Terbaru
				</h3>

				<ul class="space-y-3">
					<li class="flex gap-3 rounded-lg bg-blue-50 p-3">
						<span class="text-lg">📢</span>
						<div class="flex-1">
							<p class="text-sm font-semibold text-gray-800">
								Jadwal Operasional Libur Nasional
							</p>
							<p class="text-xs text-gray-500">
								Berlaku mulai 10 Februari 2026
							</p>
						</div>
					</li>

					<li class="flex gap-3 rounded-lg bg-yellow-50 p-3">
						<span class="text-lg">⚠️</span>
						<div class="flex-1">
							<p class="text-sm font-semibold text-gray-800">
								Reminder Pengisian Penilaian
							</p>
							<p class="text-xs text-gray-500">
								Batas akhir 15 Februari 2026
							</p>
						</div>
					</li>

					<li class="flex gap-3 rounded-lg bg-green-50 p-3">
						<span class="text-lg">🎉</span>
						<div class="flex-1">
							<p class="text-sm font-semibold text-gray-800">
								Pembayaran Gaji Periode Januari
							</p>
							<p class="text-xs text-gray-500">
								Telah ditransfer 1 Februari 2026
							</p>
						</div>
					</li>
				</ul>

				<div class="mt-3 text-center">
					<a href="#" class="text-xs text-blue-600 hover:underline">
						Lihat Semua Informasi →
					</a>
				</div>
			</div>

			{{-- Task & Reminder --}}
			<div class="card-outline card-gray rounded-2xl bg-white p-4 shadow-sm">
				<h3 class="mb-3 text-sm font-extrabold text-gray-900">
					✅ Task & Reminder
				</h3>

				<ul class="space-y-3">
					<li class="flex items-start gap-3 rounded-lg border-l-4 border-red-500 bg-red-50 p-3">
						<input type="checkbox" class="mt-1 h-4 w-4 rounded">
						<div class="flex-1">
							<p class="text-sm font-semibold text-gray-800">
								Lengkapi Penilaian Kinerja
							</p>
							<p class="text-xs text-red-600">
								⏰ Deadline: 15 Feb 2026
							</p>
						</div>
					</li>

					<li class="flex items-start gap-3 rounded-lg border-l-4 border-yellow-500 bg-yellow-50 p-3">
						<input type="checkbox" class="mt-1 h-4 w-4 rounded">
						<div class="flex-1">
							<p class="text-sm font-semibold text-gray-800">
								Setujui Pengajuan Cuti Anda
							</p>
							<p class="text-xs text-yellow-700">
								⏰ Status: Menunggu Persetujuan Manajer
							</p>
						</div>
					</li>

					<li class="flex items-start gap-3 rounded-lg border-l-4 border-blue-500 bg-blue-50 p-3">
						<input type="checkbox" class="mt-1 h-4 w-4 rounded">
						<div class="flex-1">
							<p class="text-sm font-semibold text-gray-800">
								Perbarui Data Kontak Darurat
							</p>
							<p class="text-xs text-blue-600">
								📋 Pastikan data Anda terbaru
							</p>
						</div>
					</li>
				</ul>

				<div class="mt-3 text-center">
					<a href="#" class="text-xs text-blue-600 hover:underline">
						Lihat Semua Task →
					</a>
				</div>
			</div>

		</div>

		{{-- ================= AKTIVITAS & AKSES CEPAT ================= --}}
		<div class="grid grid-cols-1 gap-4 md:grid-cols-2">

			{{-- Aktivitas Terakhir --}}
			<div class="card-outline card-gray rounded-2xl bg-white p-4 shadow-sm">
				<h3 class="mb-3 text-sm font-extrabold text-gray-900">
					🕒 Aktivitas Terakhir
				</h3>

				<ul class="space-y-2">
					<li class="flex items-center gap-3 rounded-lg bg-gray-50 p-2 text-sm">
						<span class="rounded-full bg-green-100 p-2 text-green-600">✓</span>
						<div class="flex-1">
							<p class="font-medium text-gray-800">Absensi Masuk</p>
							<p class="text-xs text-gray-500">Hari ini, 08:02</p>
						</div>
					</li>

					<li class="flex items-center gap-3 rounded-lg bg-gray-50 p-2 text-sm">
						<span class="rounded-full bg-blue-100 p-2 text-blue-600">📝</span>
						<div class="flex-1">
							<p class="font-medium text-gray-800">Ajukan Cuti</p>
							<p class="text-xs text-gray-500">3 Februari 2026</p>
						</div>
					</li>

					<li class="flex items-center gap-3 rounded-lg bg-gray-50 p-2 text-sm">
						<span class="rounded-full bg-purple-100 p-2 text-purple-600">⭐</span>
						<div class="flex-1">
							<p class="font-medium text-gray-800">Isi Penilaian</p>
							<p class="text-xs text-gray-500">1 Februari 2026</p>
						</div>
					</li>

					<li class="flex items-center gap-3 rounded-lg bg-gray-50 p-2 text-sm">
						<span class="rounded-full bg-yellow-100 p-2 text-yellow-600">👤</span>
						<div class="flex-1">
							<p class="font-medium text-gray-800">Perbarui Profil</p>
							<p class="text-xs text-gray-500">28 Januari 2026</p>
						</div>
					</li>
				</ul>
			</div>

			{{-- Akses Cepat --}}
			<div class="card-outline card-gray rounded-2xl bg-white p-4 shadow-sm">
				<h3 class="mb-3 text-sm font-extrabold text-gray-900">
					⚡ Akses Cepat
				</h3>

				<div class="grid grid-cols-2 gap-3">
					<a href="{{ route('staff.leave.index') }}"
						class="flex flex-col items-center gap-2 rounded-xl border-2 border-blue-200 bg-blue-50 p-4 text-center transition hover:border-blue-400 hover:bg-blue-100">
						<span class="text-2xl">✍️</span>
						<span class="text-sm font-semibold text-gray-800">Ajukan Cuti</span>
					</a>

					<a href="{{ route('staff.performance.index') }}"
						class="flex flex-col items-center gap-2 rounded-xl border-2 border-yellow-200 bg-yellow-50 p-4 text-center transition hover:border-yellow-400 hover:bg-yellow-100">
						<span class="text-2xl">⭐</span>
						<span class="text-sm font-semibold text-gray-800">Isi Penilaian</span>
					</a>

					<a href="#"
						class="flex flex-col items-center gap-2 rounded-xl border-2 border-green-200 bg-green-50 p-4 text-center transition hover:border-green-400 hover:bg-green-100">
						<span class="text-2xl">📋</span>
						<span class="text-sm font-semibold text-gray-800">Riwayat Kehadiran</span>
					</a>

					<a href="#"
						class="flex flex-col items-center gap-2 rounded-xl border-2 border-purple-200 bg-purple-50 p-4 text-center transition hover:border-purple-400 hover:bg-purple-100">
						<span class="text-2xl">💰</span>
						<span class="text-sm font-semibold text-gray-800">Slip Gaji</span>
					</a>

					<a href="#"
						class="flex flex-col items-center gap-2 rounded-xl border-2 border-indigo-200 bg-indigo-50 p-4 text-center transition hover:border-indigo-400 hover:bg-indigo-100">
						<span class="text-2xl">📊</span>
						<span class="text-sm font-semibold text-gray-800">Laporan Kinerja</span>
					</a>

					<a href="#"
						class="flex flex-col items-center gap-2 rounded-xl border-2 border-gray-200 bg-gray-50 p-4 text-center transition hover:border-gray-400 hover:bg-gray-100">
						<span class="text-2xl">👤</span>
						<span class="text-sm font-semibold text-gray-800">Profil Saya</span>
					</a>
				</div>
			</div>

		</div>

		{{-- ================= INFO TAMBAHAN ================= --}}
		<div class="grid grid-cols-1 gap-4 md:grid-cols-3">

			{{-- Jadwal Shift --}}
			<div class="card-outline card-gray rounded-2xl bg-white p-4 shadow-sm">
				<h3 class="mb-3 text-sm font-extrabold text-gray-900">
					🗓️ Jadwal Shift Minggu Ini
				</h3>
				<div class="space-y-2 text-sm">
					<div class="flex justify-between">
						<span class="text-gray-600">Senin - Jumat</span>
						<span class="font-semibold text-gray-800">08:00 - 17:00</span>
					</div>
					<div class="flex justify-between">
						<span class="text-gray-600">Sabtu</span>
						<span class="font-semibold text-gray-800">08:00 - 12:00</span>
					</div>
					<div class="mt-3 rounded-lg bg-blue-50 p-2 text-center">
						<p class="text-xs text-blue-700">📍 Lokasi: Kantor Pusat</p>
					</div>
				</div>
			</div>

			{{-- Tim Saya --}}
			<div class="card-outline card-gray rounded-2xl bg-white p-4 shadow-sm">
				<h3 class="mb-3 text-sm font-extrabold text-gray-900">
					👥 Tim Saya
				</h3>
				<div class="space-y-2 text-sm">
					<div class="flex items-center gap-2">
						<div class="h-8 w-8 rounded-full bg-blue-500 text-center leading-8 text-white">
							M
						</div>
						<div>
							<p class="font-semibold text-gray-800">Manager</p>
							<p class="text-xs text-gray-500">John Doe</p>
						</div>
					</div>
					<div class="mt-2 text-xs text-gray-600">
						<p>👨‍💼 Total Anggota Tim: 8 orang</p>
						<p>✅ Hadir Hari Ini: 7 orang</p>
					</div>
				</div>
			</div>

			{{-- Kontak HR --}}
			<div class="card-outline card-gray rounded-2xl bg-white p-4 shadow-sm">
				<h3 class="mb-3 text-sm font-extrabold text-gray-900">
					📞 Kontak HR
				</h3>
				<div class="space-y-2 text-sm">
					<div class="flex items-center gap-2">
						<span class="text-lg">📧</span>
						<span class="text-gray-600">hr@company.com</span>
					</div>
					<div class="flex items-center gap-2">
						<span class="text-lg">📱</span>
						<span class="text-gray-600">+62 xxx xxxx xxxx</span>
					</div>
					<div class="mt-3 rounded-lg bg-green-50 p-2 text-center">
						<p class="text-xs text-green-700">💬 Jam Operasional: 08:00 - 17:00</p>
					</div>
				</div>
			</div>

		</div>

	</div>

@endsection
