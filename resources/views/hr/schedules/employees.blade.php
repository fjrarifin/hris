@extends('layouts.app')

@section('title', 'Jadwal Karyawan')
@section('page-title', 'Jadwal Karyawan')

@section('content')
    <style>
        .employee-schedule-page {
            font-size: 12px;
        }

        .employee-schedule-card {
            border: 1px solid #e5e7eb;
            border-top: 3px solid #3b82f6;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }

        .employee-schedule-table th {
            border-top: 0;
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .employee-schedule-table td {
            vertical-align: middle;
            padding: 2px 12px;
            white-space: nowrap;

        }

        .schedule-code-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .schedule-code-list .badge {
            font-size: 11px;
            padding: 6px 8px;
        }
    </style>

    <div class="employee-schedule-page">
        @if (session('success'))
            <div class="mb-3 rounded border border-success bg-white px-3 py-2 text-success">
                {{ session('success') }}
            </div>
        @endif

        @if (session('upload_errors'))
            <div class="mb-3 rounded border border-warning bg-white px-3 py-2 text-warning">
                <div class="font-weight-bold mb-1">Sebagian data upload dilewati:</div>
                @foreach (session('upload_errors') as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-3 rounded border border-danger bg-white px-3 py-2 text-danger">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="mb-3 d-flex flex-wrap align-items-center justify-content-between">
            <div>
                <h4 class="mb-1 font-weight-bold">Jadwal Harian Karyawan</h4>
                <div class="text-muted small">
                    Halaman utama hanya menampilkan daftar karyawan. Edit jadwal dilakukan per karyawan agar halaman tetap ringan.
                </div>
            </div>
            <div class="d-flex flex-wrap">
                <a href="{{ route('hr.schedule-categories.index') }}" class="btn btn-light btn-sm font-weight-bold mr-2">
                    <i class="fas fa-list mr-1"></i> Kategori Jadwal
                </a>
                <a href="{{ route('hr.employee-schedules.template', request()->query()) }}" class="btn btn-success btn-sm font-weight-bold">
                    <i class="fas fa-file-excel mr-1"></i> Download Template
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7 mb-3">
                <div class="employee-schedule-card h-100 p-3">
                    <form method="GET" action="{{ route('hr.employee-schedules.index') }}" class="schedule-period-form row align-items-end">
                        <div class="col-md-5 mb-2">
                            <label class="small font-weight-bold text-muted mb-1">Periode Awal</label>
                            <input type="date" name="start_date" value="{{ $startDate }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-5 mb-2">
                            <label class="small font-weight-bold text-muted mb-1">Periode Akhir</label>
                            <input type="date" name="end_date" value="{{ $endDate }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2 mb-2 d-flex">
                            <button type="submit" class="btn btn-primary btn-sm btn-block font-weight-bold">
                                <i class="fas fa-search mr-1"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-5 mb-3">
                <div class="employee-schedule-card h-100 p-3">
                    <div class="mb-2 d-flex flex-wrap align-items-center justify-content-between">
                        <div class="font-weight-bold text-muted">Upload jadwal dari template</div>
                        <a href="{{ route('hr.employee-schedules.export', request()->query()) }}" class="btn btn-outline-success btn-sm font-weight-bold">
                            <i class="fas fa-download mr-1"></i> Export Jadwal Aktif
                        </a>
                    </div>
                    <form method="POST" action="{{ route('hr.employee-schedules.upload') }}" enctype="multipart/form-data" class="row align-items-end">
                        @csrf
                        <input type="hidden" name="start_date" value="{{ $startDate }}">
                        <input type="hidden" name="end_date" value="{{ $endDate }}">
                        <div class="col-md-8 mb-2">
                            <input type="file" name="file" accept=".xlsx,.xls,.csv" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <button type="submit" class="btn btn-success btn-sm btn-block font-weight-bold">
                                <i class="fas fa-upload mr-1"></i> Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="employee-schedule-card mb-3 p-3">
            <div class="mb-2 font-weight-bold text-muted">Kode jadwal aktif</div>
            <div class="schedule-code-list">
                @foreach ($categories as $category)
                    <span class="badge badge-{{ $category->is_workday ? 'primary' : 'secondary' }}">
                        {{ $category->code }}
                        @if ($category->is_workday)
                            {{ substr((string) $category->start_time, 0, 5) }}-{{ substr((string) $category->end_time, 0, 5) }}
                        @else
                            {{ $category->name }}
                        @endif
                    </span>
                @endforeach
            </div>
        </div>

        <div class="employee-schedule-card overflow-hidden">
            <div class="border-bottom p-3">
                <div class="row align-items-end">
                    <div class="col-md-7 mb-2 mb-md-0">
                        <div class="font-weight-bold">Daftar Karyawan</div>
                        <div class="text-muted small">
                            Menampilkan {{ $employees->count() }} karyawan.
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="small font-weight-bold text-muted mb-1">Cari Karyawan</label>
                        <input type="text" id="employeeScheduleDataTableSearch" class="form-control form-control-sm"
                            placeholder="Cari NIK atau nama karyawan">
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table id="employeeScheduleTable" class="employee-schedule-table table-hover table-striped mb-0 table">
                    <thead class="bg-light">
                        <tr>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Jabatan</th>
                            <th>Departemen / Unit</th>
                            <th class="text-center">Hari Terisi</th>
                            <th class="text-center" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr class="employee-schedule-row">
                                <td class="text-muted">{{ $employee->nik }}</td>
                                <td class="font-weight-bold text-dark">{{ $employee->nama_karyawan }}</td>
                                <td>{{ $employee->jabatan ?: '-' }}</td>
                                <td>
                                    <div>{{ $employee->departement ?: '-' }}</div>
                                    <div class="text-muted small">{{ $employee->unit ?: '-' }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-{{ (int) ($scheduleCounts[$employee->nik] ?? 0) > 0 ? 'primary' : 'secondary' }}">
                                        {{ (int) ($scheduleCounts[$employee->nik] ?? 0) }} hari
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('hr.employee-schedules.show', ['nik' => $employee->nik, 'start_date' => $startDate, 'end_date' => $endDate, 'q' => $q]) }}"
                                        class="btn btn-primary btn-xs font-weight-bold">
                                        <i class="fas fa-calendar-alt mr-1"></i> <span class="d-none d-md-inline text-xs">Lihat/Edit</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-5 text-center text-muted">
                                    Data karyawan tidak ditemukan untuk filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.schedule-period-form').forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    const startInput = form.querySelector('input[name="start_date"]');
                    const endInput = form.querySelector('input[name="end_date"]');

                    if (!startInput?.value || !endInput?.value) return;

                    const start = new Date(startInput.value + 'T00:00:00');
                    const end = new Date(endInput.value + 'T00:00:00');
                    const diffDays = Math.round((end - start) / 86400000);

                    if (diffDays > 45) {
                        event.preventDefault();
                        alert('Periode jadwal maksimal 46 hari.');
                        endInput.focus();
                    }
                });
            });

            if (window.jQuery && document.querySelector('.employee-schedule-row')) {
                const table = $('#employeeScheduleTable').DataTable({
                    responsive: true,
                    autoWidth: false,
                    pageLength: 25,
                    order: [[1, 'asc']],
                    dom: '<"row"<"col-12"tr>>' +
                        '<"row mt-2 px-3 pb-3"<"col-md-5"i><"col-md-7"p>>',
                    language: {
                        search: 'Cari:',
                        lengthMenu: 'Tampilkan _MENU_ data',
                        zeroRecords: 'Data tidak ditemukan',
                        info: 'Menampilkan _START_ - _END_ dari _TOTAL_ karyawan',
                        infoEmpty: 'Tidak ada data',
                        infoFiltered: '(difilter dari _MAX_ karyawan)',
                        paginate: {
                            first: 'Pertama',
                            last: 'Terakhir',
                            next: 'Selanjutnya',
                            previous: 'Sebelumnya'
                        }
                    },
                    columnDefs: [{
                        targets: 5,
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    }]
                });

                $('#employeeScheduleDataTableSearch').on('input', function() {
                    table.search(this.value).draw();
                });
            }
        });
    </script>
@endpush
