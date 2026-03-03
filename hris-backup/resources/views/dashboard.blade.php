@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')
@section('page_desc', 'Ringkasan informasi sistem HRIS')

@section('content')
<div class="grid md:grid-cols-3 gap-6">

    {{-- Card 1 --}}
    <div class="rounded-3xl border bg-white shadow-sm p-6 hover:shadow-md transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Login sebagai</p>
                <h2 class="text-xl font-extrabold mt-1 text-gray-900">{{ session('auth.nama') }}</h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ session('auth.nik') }} • {{ session('auth.jabatan') }}
                </p>
            </div>

            <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-xl">
                👤
            </div>
        </div>

        <div class="mt-5 h-1 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500"></div>
    </div>

    {{-- Card 2 --}}
    <div class="rounded-3xl border bg-white shadow-sm p-6 hover:shadow-md transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Karyawan</p>
                <h2 class="text-4xl font-extrabold mt-2 text-gray-900">5</h2>
                <p class="text-sm text-gray-600 mt-1">Dummy dulu, nanti ambil dari database</p>
            </div>

            <div class="w-12 h-12 rounded-2xl bg-green-50 flex items-center justify-center text-xl">
                📌
            </div>
        </div>

        <div class="mt-5 h-1 rounded-full bg-gradient-to-r from-green-500 to-emerald-500"></div>
    </div>

    {{-- Card 3 --}}
    <div class="rounded-3xl border bg-white shadow-sm p-6 hover:shadow-md transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Status Sistem</p>
                <h2 class="text-2xl font-extrabold mt-2 text-green-600">Aktif ✅</h2>
                <p class="text-sm text-gray-600 mt-1">Laravel + Tailwind OK</p>
            </div>

            <div class="w-12 h-12 rounded-2xl bg-purple-50 flex items-center justify-center text-xl">
                ⚡
            </div>
        </div>

        <div class="mt-5 h-1 rounded-full bg-gradient-to-r from-purple-500 to-indigo-500"></div>
    </div>

</div>

{{-- Section bawah --}}
<div class="mt-6 grid lg:grid-cols-2 gap-6">
    <div class="rounded-3xl border bg-white shadow-sm p-6">
        <h3 class="text-lg font-extrabold text-gray-900">Quick Info</h3>
        <p class="text-sm text-gray-600 mt-2">
            Next kita isi modul HRIS: Data Karyawan, Relasi Antar Karyawan, Absensi, Cuti, dll.
        </p>

        <div class="mt-5 flex flex-wrap gap-2">
            <span class="px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold">Karyawan</span>
            <span class="px-3 py-1 rounded-full bg-green-50 text-green-700 text-xs font-semibold">Relasi</span>
            <span class="px-3 py-1 rounded-full bg-purple-50 text-purple-700 text-xs font-semibold">Laporan</span>
        </div>
    </div>

    <div class="rounded-3xl border bg-white shadow-sm p-6">
        <h3 class="text-lg font-extrabold text-gray-900">Aktivitas Terakhir</h3>
        <div class="mt-4 space-y-3">
            <div class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-2xl bg-indigo-50 flex items-center justify-center">✅</span>
                    <div>
                        <p class="font-semibold">Login berhasil</p>
                        <p class="text-xs text-gray-500">Session aktif</p>
                    </div>
                </div>
                <p class="text-xs text-gray-500">Baru saja</p>
            </div>

            <div class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-2xl bg-green-50 flex items-center justify-center">📌</span>
                    <div>
                        <p class="font-semibold">Dummy data ready</p>
                        <p class="text-xs text-gray-500">m_karyawan & m_relation</p>
                    </div>
                </div>
                <p class="text-xs text-gray-500">Hari ini</p>
            </div>
        </div>
    </div>
</div>
@endsection
