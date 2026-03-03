@extends('layouts.app')

@section('title', $mode === 'create' ? 'Tambah Karyawan' : 'Edit Karyawan')
@section('page_title', $mode === 'create' ? 'Tambah Karyawan' : 'Edit Karyawan')
@section('page_desc', 'Lengkapi data karyawan')

@section('content')

@php
    $isEdit = $mode === 'edit';
@endphp

<div class="space-y-6">

    <div class="rounded-3xl border bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">
                    {{ $isEdit ? 'Edit Karyawan' : 'Tambah Karyawan' }}
                </h2>
                <p class="text-sm text-gray-500">
                    Isi data dengan benar.
                </p>
            </div>

            <a href="{{ route('admin.karyawan.index') }}"
                class="rounded-2xl bg-gray-100 px-4 py-2 text-sm font-bold text-gray-800 hover:bg-gray-200">
                ← Kembali
            </a>
        </div>
    </div>

    <form method="POST"
        action="{{ $isEdit ? route('admin.karyawan.update', $data->nik) : route('admin.karyawan.store') }}"
        class="rounded-3xl border bg-white p-6 shadow-sm space-y-5">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="grid gap-4 md:grid-cols-2">

            {{-- NIK --}}
            <div>
                <label class="text-xs font-bold text-gray-600">NIK</label>
                <input type="text" name="nik"
                    value="{{ old('nik', $data->nik) }}"
                    {{ $isEdit ? 'readonly' : '' }}
                    class="mt-1 w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $isEdit ? 'bg-gray-50' : '' }}">
                @error('nik') <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Nama --}}
            <div>
                <label class="text-xs font-bold text-gray-600">Nama Karyawan</label>
                <input type="text" name="nama_karyawan"
                    value="{{ old('nama_karyawan', $data->nama_karyawan) }}"
                    class="mt-1 w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('nama_karyawan') <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Jabatan --}}
            <div>
                <label class="text-xs font-bold text-gray-600">Jabatan</label>
                <input type="text" name="jabatan"
                    value="{{ old('jabatan', $data->jabatan) }}"
                    class="mt-1 w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm">
            </div>

            {{-- Divisi --}}
            <div>
                <label class="text-xs font-bold text-gray-600">Divisi</label>
                <input type="text" name="divisi"
                    value="{{ old('divisi', $data->divisi) }}"
                    class="mt-1 w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm">
            </div>

            {{-- Join Date --}}
            <div>
                <label class="text-xs font-bold text-gray-600">Join Date</label>
                <input type="date" name="join_date"
                    value="{{ old('join_date', $data->join_date ? \Carbon\Carbon::parse($data->join_date)->format('Y-m-d') : '') }}"
                    class="mt-1 w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm">
            </div>

        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
            <button type="submit"
                class="rounded-2xl bg-indigo-600 px-6 py-2 text-sm font-bold text-white hover:bg-indigo-700">
                {{ $isEdit ? 'Update' : 'Simpan' }}
            </button>
        </div>

    </form>

    @if($isEdit)

        <div class="rounded-3xl border bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-sm font-extrabold text-gray-900">Kontrak Karyawan</h3>
                    <p class="text-xs text-gray-500">
                        Histori kontrak untuk NIK: <span class="font-semibold">{{ $data->nik }}</span>
                    </p>
                </div>

                @if($kontrakAktif)
                    <span class="rounded-full bg-green-50 px-3 py-1 text-xs font-bold text-green-700">
                        Kontrak Aktif: #{{ $kontrakAktif->kontrak_ke }}
                    </span>
                @else
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-gray-700">
                        Tidak ada kontrak aktif
                    </span>
                @endif
            </div>

            {{-- FORM TAMBAH KONTRAK --}}
            <div class="mt-4 rounded-2xl border border-gray-200/70 bg-gray-50 p-4">
                <form method="POST" action="{{ route('admin.karyawan.kontrak.store', $data->nik) }}"
                    class="grid gap-3 md:grid-cols-4 md:items-end">
                    @csrf

                    <div>
                        <label class="text-xs font-bold text-gray-600">Start Date</label>
                        <input type="date" name="start_date" required
                            class="mt-1 w-full rounded-2xl border border-gray-200 bg-white px-4 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="text-xs font-bold text-gray-600">Durasi (bulan)</label>
                        <input type="number" name="durasi_bulan" min="1" max="60" required
                            class="mt-1 w-full rounded-2xl border border-gray-200 bg-white px-4 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="contoh 6">
                    </div>

                    <div class="md:col-span-1">
                        <label class="text-xs font-bold text-gray-600">Catatan</label>
                        <input type="text" name="catatan"
                            class="mt-1 w-full rounded-2xl border border-gray-200 bg-white px-4 py-2 text-xs"
                            placeholder="opsional">
                    </div>

                    <div class="flex justify-end">
                        <button
                            class="w-full rounded-2xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white hover:bg-indigo-700">
                            + Tambah Kontrak
                        </button>
                    </div>
                </form>
            </div>

            {{-- TABEL KONTRAK --}}
            <div class="mt-4 overflow-hidden rounded-2xl border border-gray-200/70">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="px-4 py-3 text-left">Kontrak</th>
                            <th class="px-4 py-3 text-left">Start</th>
                            <th class="px-4 py-3 text-left">End</th>
                            <th class="px-4 py-3 text-left">Durasi</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($kontrak as $k)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-semibold">#{{ $k->kontrak_ke }}</td>
                                <td class="px-4 py-3">{{ $k->start_date }}</td>
                                <td class="px-4 py-3">{{ $k->end_date }}</td>
                                <td class="px-4 py-3">{{ $k->durasi_bulan }} bln</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-bold
                                        {{ $k->status_kontrak === 'AKTIF' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                        {{ $k->status_kontrak }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">

                                        @if($k->status_kontrak === 'AKTIF')
                                            <form method="POST" action="{{ route('admin.karyawan.kontrak.finish', [$data->nik, $k->id]) }}">
                                                @csrf
                                                <button type="submit"
                                                    class="rounded-xl bg-yellow-50 px-3 py-1.5 text-[11px] font-bold text-yellow-700 hover:bg-yellow-100">
                                                    Selesai
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('admin.karyawan.kontrak.destroy', [$data->nik, $k->id]) }}"
                                            onsubmit="return confirm('Yakin hapus kontrak ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="rounded-xl bg-red-50 px-3 py-1.5 text-[11px] font-bold text-red-700 hover:bg-red-100">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    Belum ada kontrak.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>

    @endif


</div>

@endsection
