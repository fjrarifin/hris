@extends('layouts.app')

@section('title', 'Kategori Jadwal')
@section('page-title', 'Kategori Jadwal')

@section('content')
    <style>
        .schedule-page {
            font-size: 12px;
        }

        .schedule-card {
            border: 1px solid #e5e7eb;
            border-top: 3px solid #3b82f6;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }

        .schedule-table th {
            border-top: 0;
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .schedule-table td {
            vertical-align: middle;
        }
    </style>

    <div class="schedule-page">
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
                <h4 class="mb-1 font-weight-bold">Kategori Jadwal</h4>
                <div class="text-muted small">
                    Master kode shift untuk penyusunan jadwal kerja karyawan.
                </div>
            </div>
        </div>

        <div class="schedule-card mb-3 p-3">
            <form method="POST" action="{{ route('hr.schedule-categories.store') }}" class="row align-items-end">
                @csrf
                <div class="col-md-2 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">Kode</label>
                    <input type="text" name="code" value="{{ old('code') }}" class="form-control form-control-sm text-uppercase" placeholder="P0" required>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">Nama</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control form-control-sm" placeholder="Pagi 0" required>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">Tipe</label>
                    <select name="type" class="form-control form-control-sm schedule-type-select">
                        @foreach ($typeOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('type', 'work') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2 schedule-time-field">
                    <label class="small font-weight-bold text-muted mb-1">Jam Mulai</label>
                    <input type="time" name="start_time" value="{{ old('start_time') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 mb-2 schedule-time-field">
                    <label class="small font-weight-bold text-muted mb-1">Jam Selesai</label>
                    <input type="time" name="end_time" value="{{ old('end_time') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-1 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm btn-block font-weight-bold">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div class="col-12">
                    <input type="hidden" name="is_active" value="1">
                    <textarea name="description" rows="2" class="form-control form-control-sm" placeholder="Catatan opsional">{{ old('description') }}</textarea>
                </div>
            </form>
        </div>

        <div class="schedule-card overflow-hidden">
            <div class="table-responsive">
                <table class="schedule-table table-hover table-striped mb-0 table">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 90px;">Kode</th>
                            <th>Nama</th>
                            <th>Tipe</th>
                            <th>Jam</th>
                            <th>Status</th>
                            <th>Catatan</th>
                            <th class="text-center" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td>
                                    <span class="badge badge-primary px-2 py-1">{{ $category->code }}</span>
                                </td>
                                <td class="font-weight-bold">{{ $category->name }}</td>
                                <td>{{ $typeOptions[$category->type] ?? $category->type }}</td>
                                <td>
                                    @if ($category->is_workday)
                                        {{ substr((string) $category->start_time, 0, 5) }} - {{ substr((string) $category->end_time, 0, 5) }}
                                    @else
                                        <span class="text-muted">Tidak ada jam kerja</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-{{ $category->is_active ? 'success' : 'secondary' }}">
                                        {{ $category->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="text-muted">{{ $category->description ?: '-' }}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-warning btn-sm font-weight-bold" data-toggle="modal" data-target="#editScheduleCategory{{ $category->id }}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" action="{{ route('hr.schedule-categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('Hapus kategori jadwal ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm font-weight-bold">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-5 text-center text-muted">
                                    Kategori jadwal belum tersedia.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @foreach ($categories as $category)
            <div class="modal fade" id="editScheduleCategory{{ $category->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <form method="POST" action="{{ route('hr.schedule-categories.update', $category) }}" class="modal-content">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title font-weight-bold">Edit Kategori Jadwal</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <label class="small font-weight-bold text-muted mb-1">Kode</label>
                                    <input type="text" name="code" value="{{ old('code', $category->code) }}" class="form-control form-control-sm text-uppercase" required>
                                </div>
                                <div class="col-md-5 mb-2">
                                    <label class="small font-weight-bold text-muted mb-1">Nama</label>
                                    <input type="text" name="name" value="{{ old('name', $category->name) }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small font-weight-bold text-muted mb-1">Tipe</label>
                                    <select name="type" class="form-control form-control-sm schedule-type-select">
                                        @foreach ($typeOptions as $key => $label)
                                            <option value="{{ $key }}" @selected(old('type', $category->type) === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2 schedule-time-field">
                                    <label class="small font-weight-bold text-muted mb-1">Jam Mulai</label>
                                    <input type="time" name="start_time" value="{{ old('start_time', substr((string) $category->start_time, 0, 5)) }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-3 mb-2 schedule-time-field">
                                    <label class="small font-weight-bold text-muted mb-1">Jam Selesai</label>
                                    <input type="time" name="end_time" value="{{ old('end_time', substr((string) $category->end_time, 0, 5)) }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-6 mb-2 d-flex align-items-end">
                                    <div class="custom-control custom-switch">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="scheduleActive{{ $category->id }}" @checked($category->is_active)>
                                        <label class="custom-control-label font-weight-bold text-muted" for="scheduleActive{{ $category->id }}">Aktif</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="small font-weight-bold text-muted mb-1">Catatan</label>
                                    <textarea name="description" rows="3" class="form-control form-control-sm">{{ old('description', $category->description) }}</textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light btn-sm font-weight-bold" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary btn-sm font-weight-bold">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    <script>
        function refreshScheduleTimeFields(scope) {
            const select = scope.querySelector('.schedule-type-select');
            if (!select) return;

            const showTime = select.value === 'work';
            scope.querySelectorAll('.schedule-time-field').forEach(function(field) {
                field.classList.toggle('d-none', !showTime);
                field.querySelectorAll('input').forEach(function(input) {
                    input.disabled = !showTime;
                    if (!showTime) input.value = '';
                });
            });
        }

        document.querySelectorAll('form').forEach(function(form) {
            if (!form.querySelector('.schedule-type-select')) return;

            refreshScheduleTimeFields(form);
            form.querySelector('.schedule-type-select').addEventListener('change', function() {
                refreshScheduleTimeFields(form);
            });
        });
    </script>
@endpush
