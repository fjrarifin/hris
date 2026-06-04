@extends('layouts.public')

@section('title', 'Persetujuan Pengajuan')

@section('content')
<section class="card">
    <header class="card-header">
        <p class="eyebrow">Persetujuan Atasan</p>
        <h1>Tinjau Pengajuan {{ strtoupper($type) }}</h1>
        <p class="header-text">Berikan keputusan sebelum link kedaluwarsa.</p>
    </header>
    <div class="card-body">
        <div class="employee">
            <p class="label">Karyawan</p>
            <p class="value">{{ $request->user->name }}</p>
        </div>
        <div class="details">
            @if($type === 'leave')
                <div class="detail"><p class="label">Jenis Cuti</p><p class="value">{{ \App\Models\LeaveRequest::LEAVE_TYPES[$request->leave_type] ?? $request->leave_type }}</p></div>
                <div class="detail"><p class="label">Periode</p><p class="value">{{ \Carbon\Carbon::parse($request->start_date)->isoFormat('D MMM YYYY') }} - {{ \Carbon\Carbon::parse($request->end_date)->isoFormat('D MMM YYYY') }}</p></div>
                @if($request->reason)<div class="detail"><p class="label">Keterangan</p><p class="value">{{ $request->reason }}</p></div>@endif
            @elseif($type === 'ph')
                <div class="detail"><p class="label">Hari Libur</p><p class="value">{{ $request->holiday->name }} - {{ \Carbon\Carbon::parse($request->holiday->holiday_date)->isoFormat('D MMM YYYY') }}</p></div>
                <div class="detail"><p class="label">Tanggal Pengganti</p><p class="value">{{ \Carbon\Carbon::parse($request->claim_date)->isoFormat('D MMM YYYY') }}</p></div>
            @elseif($type === 'permission')
                <div class="detail"><p class="label">Jenis</p><p class="value">{{ $request->type === 'sakit' ? 'Sakit' : 'Izin Tidak Masuk' }}</p></div>
                <div class="detail"><p class="label">Tanggal</p><p class="value">{{ \Carbon\Carbon::parse($request->date)->isoFormat('D MMM YYYY') }}</p></div>
                @if($request->reason)<div class="detail"><p class="label">Alasan</p><p class="value">{{ $request->reason }}</p></div>@endif
            @endif
        </div>
        <div class="notice">Keputusan bersifat final. Pastikan data pengajuan sudah benar sebelum melanjutkan.</div>
        <div class="actions">
            <form method="POST" action="{{ route('approval.reject', $request->approval_token) }}" onsubmit="return confirm('Tolak pengajuan ini?')">@csrf<button class="button reject" type="submit">Tolak</button></form>
            <form method="POST" action="{{ route('approval.approve', $request->approval_token) }}" onsubmit="return confirm('Setujui pengajuan ini?')">@csrf<button class="button approve" type="submit">Setujui</button></form>
        </div>
    </div>
    <footer class="card-footer">Link rahasia ini berlaku selama {{ config('services.public_approval.expires_hours') }} jam dan hanya dapat digunakan satu kali.</footer>
</section>
@endsection
