@extends('layouts.public')

@section('title', 'Sudah Diproses')

@section('content')

<div class="approval-card">
    <div class="header-gradient bg-gray-600">
        <i class="fas fa-info-circle fa-2x mb-2"></i>
        <h2 class="text-lg font-bold mb-1">Pengajuan Sudah Diproses</h2>
        <p class="text-xs opacity-90">Keputusan sudah pernah diberikan</p>
    </div>

    <div class="p-5 text-center">
        <div class="mb-4">
            <i class="fas fa-check-circle text-green-500 fa-3x"></i>
        </div>

        <p class="text-sm text-gray-700">
            Pengajuan ini sudah diproses sebelumnya.
            <br>
            Link ini tidak dapat digunakan kembali.
        </p>
    </div>
</div>

@endsection
