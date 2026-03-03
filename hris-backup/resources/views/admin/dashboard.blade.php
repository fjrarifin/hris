@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('page_title', 'Admin Dashboard')
@section('page_desc', 'Panel kontrol HRIS (Admin)')

@section('content')
    <div class="grid gap-6 md:grid-cols-4">
        <div class="rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Total Karyawan</p>
            <h2 class="mt-1 text-3xl font-extrabold text-indigo-600">{{ $totalKaryawan }}</h2>
            <p class="mt-1 text-sm text-gray-600">Data aktif di master</p>
        </div>

        <div class="rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Total User Login</p>
            <h2 class="mt-1 text-3xl font-extrabold">{{ $totalUsers }}</h2>
            <p class="mt-1 text-sm text-gray-600">User terdaftar sistem</p>
        </div>

        <div class="rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Relasi Penilaian</p>
            <h2 class="mt-1 text-3xl font-extrabold">{{ $totalRelasi }}</h2>
            <p class="mt-1 text-sm text-gray-600">Mapping penilai → dinilai</p>
        </div>

        <div class="rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Submit Bulan Ini</p>
            <h2 class="mt-1 text-3xl font-extrabold text-green-600">{{ $totalSubmitBulanIni }}</h2>
            <p class="mt-1 text-sm text-gray-600">Total penilaian masuk</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 md:grid-cols-2">

        {{-- ✅ CARD: Karyawan Terbaru --}}
        <div class="rounded-3xl border border-gray-200/70 bg-white p-6 text-[12px] shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-extrabold text-gray-900">Karyawan Terbaru Masuk</h3>
                    <p class="text-sm text-gray-500">10 karyawan join terakhir</p>
                </div>

                <a href="{{ route('admin.karyawan.index') }}"
                    class="rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">
                    👥 Kelola
                </a>
            </div>

            <div class="mt-5 space-y-3">
                @forelse ($karyawanTerbaru as $row)
                    <div class="flex items-center justify-between rounded-2xl border border-gray-200/70 px-4 py-1">
                        <div>
                            <p class="font-bold text-gray-900">{{ $row->nama_karyawan }}</p>
                            <p class="text-xs text-gray-500">{{ $row->jabatan ?? '-' }}</p>
                        </div>

                        <div class="text-right">
                            <p class="text-xs font-semibold text-indigo-600">
                                {{ \Carbon\Carbon::parse($row->join_date)->format('d M Y') }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 p-5 text-center">
                        <p class="text-sm font-semibold text-gray-700">Belum ada data join_date</p>
                        <p class="mt-1 text-xs text-gray-500">Isi kolom join_date untuk menampilkan list terbaru masuk.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ✅ CARD: Kontrak Akan Habis --}}
        <div class="rounded-3xl border border-gray-200/70 bg-white p-6 text-[12px] shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-extrabold text-gray-900">Kontrak Akan Habis</h3>
                    <p class="text-sm text-gray-500">≤ 1 bulan dari hari ini</p>
                </div>

                <a href="{{ route('admin.karyawan.index') }}"
                    class="rounded-2xl bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 transition hover:bg-gray-300">
                    🔎 Lihat Semua
                </a>
            </div>

            <div class="mt-5 space-y-3">
                @forelse ($kontrakHabisSebulan as $row)
                    @php
                        $end = \Carbon\Carbon::parse($row->end_date);
                        $sisaHari = now()->diffInDays($end, false);
                    @endphp

                    <div class="flex items-center justify-between rounded-2xl border border-gray-200/70 px-4 py-1">
                        <div>
                            <p class="font-bold text-gray-900">{{ $row->nama_karyawan }}</p>
                            <p class="text-xs text-gray-500">{{ $row->nik }} • {{ $row->jabatan ?? '-' }}</p>
                        </div>

                        <div class="text-right">
                            <p class="text-xs font-extrabold text-red-600">
                                {{ $end->format('d M Y') }}
                            </p>
                            <p class="text-xs text-gray-500">
                                @if ($sisaHari < 0)
                                    Sudah lewat
                                @elseif ($sisaHari === 0)
                                    Hari ini
                                @else
                                    {{ $sisaHari }} hari lagi
                                @endif
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 p-5 text-center">
                        <p class="text-sm font-semibold text-gray-700">Tidak ada kontrak yang mau habis</p>
                        <p class="mt-1 text-xs text-gray-500">Isi kolom end_date untuk monitoring kontrak.</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
@endsection
