@extends('layouts.public')

@section('title', 'Link Expired')

@section('content')

<div class="approval-card">
    <div class="header-gradient bg-red-600">
        <i class="fas fa-clock fa-2x mb-2"></i>
        <h2 class="text-lg font-bold mb-1">Link Expired</h2>
        <p class="text-xs opacity-90">Waktu approval telah habis</p>
    </div>

    <div class="p-5 text-center">
        <div class="mb-4">
            <i class="fas fa-exclamation-triangle text-red-500 fa-3x"></i>
        </div>

        <p class="text-sm text-gray-700">
            Link approval sudah melewati batas waktu.
            <br>
            Silakan minta pengajuan baru jika diperlukan.
        </p>
    </div>
</div>

@endsection
