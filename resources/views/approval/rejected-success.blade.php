@extends('layouts.public')

@section('title', 'Pengajuan Ditolak')

@section('content')

<div class="approval-card">
    <div class="header-gradient bg-red-600">
        <i class="fas fa-times-circle fa-2x mb-2"></i>
        <h2 class="text-lg font-bold mb-1">Pengajuan Ditolak</h2>
        <p class="text-xs opacity-90">Keputusan berhasil disimpan</p>
    </div>

    <div class="p-5 text-center">
        <div class="mb-4">
            <i class="fas fa-thumbs-down text-red-500 fa-3x"></i>
        </div>

        <p class="text-sm text-gray-700">
            Pengajuan telah ditolak.
            <br>
            Staff akan menerima notifikasi keputusan ini.
        </p>
    </div>
</div>

@endsection
