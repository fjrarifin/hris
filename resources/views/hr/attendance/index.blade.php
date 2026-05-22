@extends('layouts.app')

@section('title', 'Log Absensi Fingerspot')
@section('page-title', 'Log Absensi Fingerspot')

@section('content')
    <style>
        .attendance-card {
            border: 1px solid #e5e7eb;
            border-top: 3px solid #3b82f6;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }

        .attendance-stat {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            padding: 12px 14px;
        }

        .attendance-stat .label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
            color: #64748b;
        }

        .attendance-stat .value {
            margin-top: 4px;
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
        }

        .attendance-table {
            font-size: 12px;
        }

        .attendance-table th {
            border-top: 0;
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .attendance-table td {
            vertical-align: middle;
            white-space: nowrap;
        }
    </style>

    @php
        $lastSync = $summary['last_sync'] ? \Carbon\Carbon::parse($summary['last_sync']) : null;
    @endphp

    <div class="mb-3 d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <h4 class="mb-1 font-weight-bold">Log Absensi Fingerspot</h4>
            <div class="text-muted small">
                Data diambil otomatis dari Fingerspot setiap pukul 23:00 dan disimpan ke database.
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="mr-2 text-muted small">
                Sync terakhir:
                <strong>{{ $lastSync ? $lastSync->format('d/m/Y H:i') : '-' }}</strong>
            </div>
            <a href="{{ route('hr.attendance.export', request()->query()) }}" class="btn btn-success btn-sm font-weight-bold">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
            </a>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4 mb-2">
            <div class="attendance-stat">
                <div class="label">Total Scan Periode</div>
                <div class="value">{{ number_format($summary['period_total']) }}</div>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="attendance-stat">
                <div class="label">PIN Unik Periode</div>
                <div class="value text-success">{{ number_format($summary['unique_pin']) }}</div>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="attendance-stat">
                <div class="label">Hasil Filter</div>
                <div class="value text-primary">{{ number_format($summary['total']) }}</div>
            </div>
        </div>
    </div>

    <div class="attendance-card mb-3 p-3">
        <form method="GET" action="{{ route('hr.attendance.index') }}" class="row align-items-end">
            <div class="col-md-3 mb-2">
                <label class="small font-weight-bold text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="start_date" value="{{ $startDate }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 mb-2">
                <label class="small font-weight-bold text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" value="{{ $endDate }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 mb-2">
                <label class="small font-weight-bold text-muted mb-1">Cari Nama / ID / Cloud ID</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Contoh: Budi atau 369">
            </div>
            <div class="col-md-2 mb-2">
                <label class="small font-weight-bold text-muted mb-1">Tipe Absensi</label>
                <select name="status_scan" class="form-control form-control-sm">
                    <option value="">Semua</option>
                    <option value="0" @selected($statusScan === '0')>0</option>
                    <option value="1" @selected($statusScan === '1')>1</option>
                </select>
            </div>
            <div class="col-md-1 mb-2 d-flex">
                <button type="submit" class="btn btn-primary btn-sm btn-block font-weight-bold">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            <div class="col-12 mt-1 d-flex flex-wrap justify-content-between">
                <a href="{{ route('hr.attendance.index') }}" class="btn btn-light btn-sm font-weight-bold">
                    <i class="fas fa-redo mr-1"></i> Reset
                </a>
                <a href="{{ route('hr.attendance.export', request()->query()) }}" class="btn btn-success btn-sm font-weight-bold">
                    <i class="fas fa-file-excel mr-1"></i> Export Excel
                </a>
            </div>
        </form>
    </div>

    <div class="attendance-card overflow-hidden">
        <div class="table-responsive">
            <table class="attendance-table table-hover table-striped mb-0 table">
                <thead class="bg-light">
                    <tr>
                        <th>Cloud ID</th>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Tanggal Absensi</th>
                        <th>Jam Absensi</th>
                        <th class="text-center">Verifikasi</th>
                        <th class="text-center">Tipe Absensi</th>
                        <th>Jabatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        @php
                            $scanDate = $log->scan_date ? \Carbon\Carbon::parse($log->scan_date) : null;
                            $attendanceType = match ((string) $log->status_scan) {
                                '0' => 'Absen Masuk',
                                '1' => 'Absen Keluar',
                                default => $log->status_scan ?? '-',
                            };
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $log->cloud_id ?? '-' }}</td>
                            <td class="font-weight-bold">{{ $log->pin ?? '-' }}</td>
                            <td>
                                <div class="font-weight-bold text-dark">{{ $log->karyawan?->nama_karyawan ?? '-' }}</div>
                                <div class="text-muted small">NIK: {{ $log->karyawan?->nik ?? '-' }}</div>
                            </td>
                            <td>{{ $scanDate ? $scanDate->format('d/m/Y') : '-' }}</td>
                            <td>{{ $scanDate ? $scanDate->format('H:i:s') : '-' }}</td>
                            <td class="text-center">
                                <span class="badge badge-light">{{ $log->verify ?? '-' }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-primary">{{ $attendanceType }}</span>
                            </td>
                            <td>{{ $log->karyawan?->jabatan ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-5 text-center text-muted">
                                Data absensi belum tersedia untuk filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-top d-flex flex-wrap align-items-center justify-content-between p-3">
            <div class="text-muted small">
                Menampilkan {{ $logs->firstItem() ?? 0 }} - {{ $logs->lastItem() ?? 0 }} dari {{ $logs->total() }} data
            </div>
            <div>
                {{ $logs->links() }}
            </div>
        </div>
    </div>
@endsection
