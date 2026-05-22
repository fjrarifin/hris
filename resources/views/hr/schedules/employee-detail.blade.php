@extends('layouts.app')

@section('title', 'Detail Jadwal Karyawan')
@section('page-title', 'Detail Jadwal Karyawan')

@section('content')
    <style>
        .employee-schedule-detail {
            font-size: 12px;
        }

        .employee-schedule-card {
            border: 1px solid #e5e7eb;
            border-top: 3px solid #3b82f6;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }

        .schedule-day-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
        }

        .schedule-day-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            padding: 10px;
        }

        .schedule-day-item.weekend {
            background: #f8fafc;
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

    <div class="employee-schedule-detail">
        @if (session('success'))
            <div class="mb-3 rounded border border-success bg-white px-3 py-2 text-success">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-3 rounded border border-danger bg-white px-3 py-2 text-danger">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="mb-3 d-flex flex-wrap align-items-center justify-content-between">
            <div>
                <h4 class="mb-1 font-weight-bold">{{ $employee->nama_karyawan }}</h4>
                <div class="text-muted small">
                    NIK: {{ $employee->nik }} &bull; {{ $employee->jabatan ?: '-' }} &bull; {{ $employee->departement ?: '-' }}
                </div>
            </div>
            <a href="{{ route('hr.employee-schedules.index', ['start_date' => $startDate, 'end_date' => $endDate, 'q' => $q]) }}"
                class="btn btn-light btn-sm font-weight-bold">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>

        <div class="employee-schedule-card mb-3 p-3">
            <form method="GET" action="{{ route('hr.employee-schedules.show', $employee->nik) }}" class="schedule-period-form row align-items-end">
                <input type="hidden" name="q" value="{{ $q }}">
                <div class="col-md-4 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">Periode Awal</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-4 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">Periode Akhir</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-4 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold">
                        <i class="fas fa-search mr-1"></i> Tampilkan
                    </button>
                </div>
            </form>
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

        <form method="POST" action="{{ route('hr.employee-schedules.store') }}">
            @csrf
            <input type="hidden" name="start_date" value="{{ $startDate }}">
            <input type="hidden" name="end_date" value="{{ $endDate }}">

            <div class="employee-schedule-card overflow-hidden">
                <div class="border-bottom d-flex flex-wrap align-items-center justify-content-between p-3">
                    <div>
                        <div class="font-weight-bold">Jadwal Periode</div>
                        <div class="text-muted small">{{ $dates->count() }} hari</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan Jadwal
                    </button>
                </div>
                <div class="p-3">
                    <div class="schedule-day-grid">
                        @foreach ($dates as $date)
                            @php
                                $dateKey = $date->format('Y-m-d');
                                $schedule = $schedules->get($dateKey);
                                $selectedCode = $schedule?->schedule_code;
                            @endphp
                            <div class="schedule-day-item @if($date->isWeekend()) weekend @endif">
                                <div class="mb-2 d-flex align-items-start justify-content-between">
                                    <div>
                                        <div class="font-weight-bold">{{ $date->format('d/m/Y') }}</div>
                                        <div class="text-muted small">{{ $date->translatedFormat('l') }}</div>
                                    </div>
                                    @if ($selectedCode)
                                        <span class="badge badge-primary">{{ $selectedCode }}</span>
                                    @endif
                                </div>
                                <select name="schedules[{{ $employee->nik }}][{{ $dateKey }}]" class="form-control form-control-sm">
                                    <option value="">Kosong</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->code }}" @selected($selectedCode === $category->code)>
                                            {{ $category->code }} - {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </form>
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
        });
    </script>
@endpush
