@extends('layouts.app')

@section('title', 'Log Absensi Fingerspot')
@section('page-title', 'Log Absensi Fingerspot')

@section('content')
    @php
        $lastSync = $summary['last_sync'] ? \Carbon\Carbon::parse($summary['last_sync']) : null;
    @endphp

    <div class="mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h4 class="mb-1 font-weight-bold">Log Absensi Fingerspot</h4>
            <div class="text-muted small">
                Data diambil otomatis dari Fingerspot setiap pukul 23:00 dan disimpan ke database.
            </div>
        </div>
        <div class="text-muted small">
            Sync terakhir:
            <strong>{{ $lastSync ? $lastSync->format('d/m/Y H:i') : '-' }}</strong>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ number_format($summary['period_total']) }}</h3>
                    <p>Total scan periode</p>
                </div>
                <div class="icon"><i class="fas fa-fingerprint"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ number_format($summary['unique_pin']) }}</h3>
                    <p>PIN unik periode</p>
                </div>
                <div class="icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>{{ number_format($summary['total']) }}</h3>
                    <p>Hasil filter</p>
                </div>
                <div class="icon"><i class="fas fa-filter"></i></div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title font-weight-bold">Filter Data</h3>
        </div>
        <form method="GET" action="{{ route('hr.attendance.index') }}">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label class="small text-muted mb-1">Dari Tanggal</label>
                        <input type="date" name="start_date" value="{{ $startDate }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted mb-1">Sampai Tanggal</label>
                        <input type="date" name="end_date" value="{{ $endDate }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted mb-1">Cari Nama / NIK / PIN / Source</label>
                        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Contoh: Budi atau 0147">
                    </div>
                    <div class="col-md-2">
                        <label class="small text-muted mb-1">Status Scan</label>
                        <select name="status_scan" class="form-control">
                            <option value="">Semua</option>
                            <option value="0" @selected($statusScan === '0')>0</option>
                            <option value="1" @selected($statusScan === '1')>1</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="card-body table-responsive p-0">
            <table class="table-hover table-striped mb-0 table">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th style="width: 160px;">Tanggal Scan</th>
                        <th>PIN</th>
                        <th>Nama Karyawan</th>
                        <th>NIK</th>
                        <th class="text-center">Verify</th>
                        <th class="text-center">Status</th>
                        <th>Source</th>
                        <th>Trans ID</th>
                        <th>Cloud ID</th>
                        <th style="min-width: 260px;">Raw Payload</th>
                        <th>Created</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        @php
                            $scanDate = $log->scan_date ? \Carbon\Carbon::parse($log->scan_date) : null;
                            $createdAt = $log->created_at ? \Carbon\Carbon::parse($log->created_at) : null;
                            $updatedAt = $log->updated_at ? \Carbon\Carbon::parse($log->updated_at) : null;
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $log->id }}</td>
                            <td>
                                <div class="font-weight-bold">{{ $scanDate ? $scanDate->format('d/m/Y') : '-' }}</div>
                                <div class="text-muted small">{{ $scanDate ? $scanDate->format('H:i:s') : '-' }}</div>
                            </td>
                            <td class="font-weight-bold">{{ $log->pin }}</td>
                            <td>{{ $log->karyawan?->nama_karyawan ?? '-' }}</td>
                            <td class="small text-muted">{{ $log->karyawan?->nik ?? '-' }}</td>
                            <td class="text-center">
                                <span class="badge badge-light">{{ $log->verify ?? '-' }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-primary">{{ $log->status_scan ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $log->source === 'webhook' ? 'success' : ($log->source === 'scheduled' ? 'warning' : 'info') }}">
                                    {{ strtoupper($log->source) }}
                                </span>
                            </td>
                            <td class="small text-muted">{{ $log->trans_id ?? '-' }}</td>
                            <td class="small text-muted">{{ $log->cloud_id ?? '-' }}</td>
                            <td>
                                <code class="small text-wrap d-block" style="white-space: pre-wrap;">
{{ json_encode($log->raw_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '-' }}
                                </code>
                            </td>
                            <td class="small text-muted">{{ $createdAt ? $createdAt->format('d/m/Y H:i') : '-' }}</td>
                            <td class="small text-muted">{{ $updatedAt ? $updatedAt->format('d/m/Y H:i') : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="py-5 text-center text-muted">
                                Data absensi belum tersedia untuk filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap align-items-center justify-content-between">
            <div class="text-muted small">
                Menampilkan {{ $logs->firstItem() ?? 0 }} - {{ $logs->lastItem() ?? 0 }} dari {{ $logs->total() }} data
            </div>
            {{ $logs->links() }}
        </div>
    </div>
@endsection
