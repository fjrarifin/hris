@extends('layouts.gams-public')

@section('title', 'GAMS — Tautan Persetujuan Tidak Valid')

@section('content')
<section class="gams-card">
    <header class="gams-brand-bar is-danger">
        <svg class="gams-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-label="GAMS Logo">
            <rect width="512" height="512" rx="96" fill="#0b162c" />
            <path d="M96 160 L256 94 L416 160 L416 208 L370 208 L370 182 L256 136 L142 182 L142 364 L168 364 L168 406 L80 406 L80 364 L96 364 Z" fill="#ffffff" />
            <path d="M318 248 L416 248 L416 364 L432 364 L432 406 L344 406 L344 364 L370 364 L370 292 L318 292 Z" fill="#ffffff" />
            <path d="M190 186 L232 169 L232 406 L190 406 Z" fill="#3b82f6" />
            <path d="M254 169 L296 186 L296 406 L254 406 Z" fill="#60a5fa" />
        </svg>
        <div>
            <h1>Tautan Tidak Valid</h1>
            <p>GAMS &middot; General Affairs Management System</p>
        </div>
    </header>

    <div class="gams-body">
        <div class="gams-banner gams-banner-err" role="alert">
            {{ $reason ?? 'Tautan persetujuan tidak valid atau sudah tidak berlaku.' }}
        </div>
        <div class="gams-done-note">
            Silakan buka tautan terbaru dari pesan WhatsApp, atau hubungi Admin GA/IT jika masalah berlanjut.
        </div>
    </div>
</section>
@endsection
