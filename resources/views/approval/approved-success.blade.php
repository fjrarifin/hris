@extends('layouts.public')

@section('title', 'Berhasil Disetujui')

@section('content')

<div class="approval-card">
    <div class="header-gradient bg-green-600">
        <i class="fas fa-check-circle fa-2x mb-2"></i>
        <h2 class="text-lg font-bold mb-1">Pengajuan Disetujui</h2>
        <p class="text-xs opacity-90">Keputusan berhasil disimpan</p>
    </div>

    <div class="p-5 text-center">
        <div class="mb-4">
            <i class="fas fa-thumbs-up text-green-500 fa-3x"></i>
        </div>

        <p class="text-sm text-gray-700">
            Pengajuan telah berhasil disetujui.
            <br>
            Terima kasih atas keputusan Anda.
        </p>
    </div>
</div>

@endsection
