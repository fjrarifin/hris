@extends('layouts.public')

@section('title', 'Persetujuan Pengajuan')

@section('content')
<section class="card">
    <header class="card-header">
        <p class="eyebrow">Persetujuan Atasan</p>
        <h1>Tinjau Pengajuan {{ $type === 'recruitment' ? 'Rekrutmen (Manpower)' : strtoupper($type) }}</h1>
        <p class="header-text">Berikan keputusan sebelum link kedaluwarsa.</p>
    </header>
    <div class="card-body">
        <div class="employee">
            <p class="label">{{ $type === 'recruitment' ? 'Diajukan Oleh' : 'Karyawan' }}</p>
            <p class="value">{{ $request->requester?->nama_karyawan ?: ($request->user?->name ?: $request->requester_nik) }}</p>
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
            @elseif($type === 'overtime')
                <div class="detail"><p class="label">Tanggal Lembur</p><p class="value">{{ \Carbon\Carbon::parse($request->date)->isoFormat('D MMM YYYY') }}</p></div>
                <div class="detail"><p class="label">Jam Lembur</p><p class="value">{{ $request->start_time }} - {{ $request->end_time }}</p></div>
                @if($request->reason)<div class="detail"><p class="label">Pekerjaan / Alasan</p><p class="value">{{ $request->reason }}</p></div>@endif
            @elseif($type === 'extra_off')
                <div class="detail"><p class="label">Tanggal Extra Off</p><p class="value">{{ \Carbon\Carbon::parse($request->claim_date)->isoFormat('D MMM YYYY') }}</p></div>
                @if($request->notes)<div class="detail"><p class="label">Keterangan</p><p class="value">{{ $request->notes }}</p></div>@endif
            @elseif($type === 'recruitment')
                <div class="detail"><p class="label">Posisi Dibutuhkan</p><p class="value font-semibold">{{ $request->title }}</p></div>
                <div class="detail"><p class="label">Departemen / Unit</p><p class="value">{{ $request->department }} - {{ $request->unit }}</p></div>
                <div class="detail"><p class="label">Jumlah Karyawan</p><p class="value">{{ $request->quantity }} Orang</p></div>
                <div class="detail"><p class="label">Jenis Hiring</p><p class="value">{{ $request->hiring_type === 'replacement' ? 'Replacement' : 'New Hiring' }}</p></div>
                @if($request->hiring_type === 'replacement')
                    <div class="detail"><p class="label">Menggantikan</p><p class="value">{{ $request->replaced_employee_name }} ({{ $request->replaced_employee_position }})</p></div>
                @endif
                <div class="detail"><p class="label">Status Kerja Target</p><p class="value">{{ strtoupper($request->employment_status ?: 'PKWT') }}</p></div>
                @if($request->direct_report_name)
                    <div class="detail"><p class="label">Direct Report</p><p class="value">{{ $request->direct_report_name }}</p></div>
                @endif
                @if(is_array($request->subordinates) && count($request->subordinates) > 0)
                    @php
                        $subNames = array_map(fn($s) => is_array($s) ? ($s['nama_karyawan'] ?? $s['nama'] ?? '') : (string)$s, $request->subordinates);
                    @endphp
                    <div class="detail"><p class="label">Subordinate</p><p class="value">{{ implode(', ', array_filter($subNames)) }}</p></div>
                @endif
                <div class="detail"><p class="label">Target Mulai</p><p class="value">{{ $request->start_date ? \Carbon\Carbon::parse($request->start_date)->isoFormat('D MMM YYYY') : '-' }}</p></div>
                <div class="detail"><p class="label">Tahap Persetujuan</p><p class="value font-bold text-primary">{{ strtoupper($request->approval_step) }}</p></div>
                @if($request->description)<div class="detail"><p class="label">Keterangan / Alasan</p><p class="value">{{ $request->description }}</p></div>@endif
            @endif
        </div>
        <div class="notice">Keputusan bersifat final. Pastikan data pengajuan sudah benar sebelum melanjutkan.</div>
        <div class="actions">
            <form method="POST" action="{{ route('approval.reject', $request->approval_token) }}" onsubmit="return confirm('Tolak pengajuan ini?')">@csrf<button class="button reject" type="submit">Tolak</button></form>
            <form method="POST" action="{{ route('approval.approve', $request->approval_token) }}" onsubmit="return confirm('Setujui pengajuan ini?')">@csrf<button class="button approve" type="submit">Setujui</button></form>
        </div>
    </div>
    <footer class="card-footer">Link ini berlaku selama {{ config('services.public_approval.expires_hours', 72) }} jam dan hanya dapat digunakan satu kali.</footer>
</section>
@endsection
