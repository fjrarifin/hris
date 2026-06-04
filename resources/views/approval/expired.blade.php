@extends('layouts.public')
@section('title', 'Link Kedaluwarsa')
@section('content')
<section class="card"><header class="card-header danger"><p class="eyebrow">Link Kedaluwarsa</p><h1>Waktu persetujuan telah habis</h1></header><div class="card-body"><p class="message">Link approval sudah melewati batas waktu {{ config('services.public_approval.expires_hours') }} jam. Silakan proses pengajuan dari aplikasi atau minta pengajuan baru bila diperlukan.</p></div></section>
@endsection
