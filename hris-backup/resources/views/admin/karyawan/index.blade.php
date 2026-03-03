@extends('layouts.app')

@section('title', 'Master Karyawan')
@section('page_title', 'Master Karyawan')
@section('page_desc', 'Kelola data karyawan HRIS')

@section('content')

<div class="space-y-6">

    <div class="rounded-3xl border bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">Data Karyawan</h2>
                <p class="text-sm text-gray-500">
                    Total: <span class="font-semibold">{{ $karyawan->total() }}</span>
                </p>
            </div>

            <div class="flex flex-col gap-2 md:flex-row md:items-center">
                <form method="GET" class="flex gap-2">
                    <input type="text" name="q" value="{{ $q }}"
                        placeholder="Cari NIK / Nama / Jabatan..."
                        class="w-full md:w-72 rounded-2xl border border-gray-200 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button class="rounded-2xl bg-gray-100 px-4 py-2 text-sm font-bold text-gray-800 hover:bg-gray-200">
                        Cari
                    </button>
                </form>

                <a href="{{ route('admin.karyawan.create') }}"
                    class="rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">
                    + Tambah
                </a>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-3xl border bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="px-4 py-3 text-left">NIK</th>
                    <th class="px-4 py-3 text-left">Nama</th>
                    <th class="px-4 py-3 text-left">Jabatan</th>
                    <th class="px-4 py-3 text-left">Divisi</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($karyawan as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $row->nik }}</td>
                        <td class="px-4 py-3">{{ $row->nama_karyawan }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $row->jabatan }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $row->divisi }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex justify-center gap-2">
                                <a href="{{ route('admin.karyawan.edit', $row->nik) }}"
                                    class="rounded-xl bg-gray-100 px-3 py-1.5 text-xs font-bold hover:bg-gray-200">
                                    Edit
                                </a>

                                <form method="POST" action="{{ route('admin.karyawan.destroy', $row->nik) }}"
                                    onsubmit="return confirm('Yakin hapus karyawan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-xl bg-red-50 px-3 py-1.5 text-xs font-bold text-red-700 hover:bg-red-100">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-gray-500">
                            Data kosong.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $karyawan->links() }}
    </div>

</div>

@endsection
