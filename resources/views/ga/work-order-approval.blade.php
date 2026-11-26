@extends('layouts.gams-public')

@section('title', 'GAMS — Persetujuan Work Order')

@section('content')
@php
    $stageValue = (string) ($approval->stage ?? 'initial');
    $isSphStage = str_starts_with($stageValue, 'sph');
    $isRabStage = str_starts_with($stageValue, 'rab');
    $stageRound = preg_match('/_(\d+)$/', $stageValue, $m) ? (int) $m[1] : 1;
    $stageTitle = $isSphStage ? 'Persetujuan SPH Vendor' : ($isRabStage ? 'Persetujuan RAB Work Order' : 'Persetujuan Work Order');
    $statusLabels = [
        'WAITING_APPROVAL' => 'Menunggu Persetujuan',
        'PENDING_GA' => 'Menunggu Review GA',
        'RAB_PENDING' => 'Menunggu Persetujuan RAB',
        'RAB_ROUND_APPROVED' => 'Ronde RAB Disetujui',
        'RAB_APPROVED' => 'RAB Disetujui',
        'SPH_PENDING' => 'Menunggu Persetujuan SPH',
        'SPH_ROUND_APPROVED' => 'Ronde SPH Disetujui',
        'SPH_APPROVED' => 'SPH Disetujui',
        'SPL_PENDING' => 'Menunggu Finance (SPL)',
        'BUYING_MATERIAL' => 'Pembelian Material',
        'ON_PROGRESS' => 'Sedang Dikerjakan',
        'PROGRES' => 'Sedang Dikerjakan',
        'REVIEW' => 'Menunggu Review Pemohon',
        'DONE' => 'Selesai',
        'HOLD' => 'Ditunda',
        'REJECTED' => 'Ditolak',
        'BATAL' => 'Dibatalkan',
    ];
    $rawStatus = (string) ($workOrder->status ?? '');
    $statusLabel = str_starts_with($rawStatus, 'REV-')
        ? 'Revisi '.substr($rawStatus, 4)
        : ($statusLabels[$rawStatus] ?? $rawStatus);
    $statusPillClass = in_array($rawStatus, ['DONE', 'RAB_APPROVED', 'SPH_APPROVED'], true)
        ? 'gams-pill-ok'
        : (in_array($rawStatus, ['REJECTED', 'BATAL'], true) || str_starts_with($rawStatus, 'REV-')
            ? 'gams-pill-err'
            : (in_array($rawStatus, ['ON_PROGRESS', 'PROGRES', 'BUYING_MATERIAL'], true)
                ? 'gams-pill-info'
                : 'gams-pill-warn'));
    $approvalStatusLabels = ['approved' => 'Disetujui', 'rejected' => 'Ditolak', 'pending' => 'Menunggu'];
    $photoPaths = is_array($workOrder->photo_paths ?? null)
        ? array_values(array_filter($workOrder->photo_paths))
        : (! empty($workOrder->photo_path) ? [$workOrder->photo_path] : []);
    $attachmentBase = url('/api/public/ga-work-orders/'.$token.'/attachments');
    $formatRp = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $hasAttachments = ! empty($photoPaths)
        || ($workOrder->request_document_path ?? null)
        || ($workOrder->spl_document_path ?? null)
        || ($workOrder->sph_document_path ?? null);
@endphp

<section class="gams-card">
    <header class="gams-brand-bar">
        <svg class="gams-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-label="GAMS Logo">
            <rect width="512" height="512" rx="96" fill="#0b162c" />
            <path d="M96 160 L256 94 L416 160 L416 208 L370 208 L370 182 L256 136 L142 182 L142 364 L168 364 L168 406 L80 406 L80 364 L96 364 Z" fill="#ffffff" />
            <path d="M318 248 L416 248 L416 364 L432 364 L432 406 L344 406 L344 364 L370 364 L370 292 L318 292 Z" fill="#ffffff" />
            <path d="M190 186 L232 169 L232 406 L190 406 Z" fill="#3b82f6" />
            <path d="M254 169 L296 186 L296 406 L254 406 Z" fill="#60a5fa" />
        </svg>
        <div>
            <h1>{{ $stageTitle }}</h1>
            <p>GAMS &middot; General Affairs Management System</p>
        </div>
    </header>

    <div class="gams-body">
        @if (session('status'))
            <div class="gams-banner gams-banner-ok" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="gams-banner gams-banner-err" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="gams-meta-head">
            <div>
                <span class="gams-req-no">
                    WO #{{ $workOrder->excel_no ?: $workOrder->id }}
                    @if ($isRabStage || $isSphStage) &middot; Ronde {{ $stageRound }} @endif
                </span>
                <h2>{{ $workOrder->area_name ?: 'Work Order' }} &middot; {{ $workOrder->job_type ?: 'WORK ORDER' }}</h2>
            </div>
            <span class="gams-pill {{ $statusPillClass }}">{{ $statusLabel }}</span>
        </div>

        <div class="gams-info-grid">
            <div class="gams-info-item">
                <span>Pemohon</span>
                <strong>{{ $requester->name ?? '-' }}</strong>
                @if (! empty($requester->nik))
                    <small>NIK {{ $requester->nik }}</small>
                @endif
            </div>
            <div class="gams-info-item">
                <span>Approver</span>
                <strong>{{ $approver->name ?? '-' }}</strong>
                <small>Tahap: {{ $isSphStage ? 'SPH Vendor (Ronde '.$stageRound.')' : ($isRabStage ? ($stageRound === 1 ? 'RAB Tahap 1 (Internal GA)' : ($stageRound === 2 ? 'RAB Tahap 2 (User)' : 'RAB Biaya (Ronde '.$stageRound.')')) : 'Persetujuan Awal') }}</small>
            </div>
            <div class="gams-info-item">
                <span>Tanggal Pengajuan</span>
                <strong>{{ $workOrder->request_date ?: '-' }}</strong>
            </div>
            <div class="gams-info-item">
                <span>Target Selesai</span>
                <strong>{{ $workOrder->target_date ?: '-' }}</strong>
            </div>
            @if (($workOrder->sph_document_path ?? null) || (float) ($workOrder->sph_amount ?? 0) > 0)
                <div class="gams-info-item">
                    <span>Vendor Eksternal</span>
                    <strong>{{ $workOrder->vendor_name ?: '-' }}</strong>
                </div>
                <div class="gams-info-item">
                    <span>Nominal SPH</span>
                    <strong>{{ $formatRp($workOrder->sph_amount ?? 0) }}</strong>
                </div>
            @endif
            @if ($isRabStage || (float) ($workOrder->rab_total ?? 0) > 0)
                <div class="gams-info-item">
                    <span>Total RAB</span>
                    <strong>{{ $formatRp($workOrder->rab_total ?? 0) }}</strong>
                </div>
            @endif
        </div>

        <div class="gams-desc-box">
            <span class="gams-desc-label">Deskripsi Pekerjaan / Kerusakan</span>
            {{ $workOrder->project_description ?: '-' }}
        </div>

        @if ($workOrder->notes)
            <div class="gams-desc-box">
                <span class="gams-desc-label">Catatan Pengajuan</span>
                {{ $workOrder->notes }}
            </div>
        @endif

        @if ($workOrder->rab_notes)
            <div class="gams-desc-box">
                <span class="gams-desc-label">Catatan RAB</span>
                {{ $workOrder->rab_notes }}
            </div>
        @endif

        @if (! empty($rabItems))
            <div class="gams-section">
                <h3>Rincian RAB Material &amp; Biaya</h3>
                <div class="gams-table-wrap">
                    <table class="gams-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="num">Qty</th>
                                <th class="num">Harga Satuan</th>
                                <th class="num">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rabItems as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->item_name }}</strong>
                                        @if (! empty($item->specification))
                                            <span class="gams-sub">{{ $item->specification }}</span>
                                        @endif
                                    </td>
                                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->qty, 2, ',', '.'), '0'), ',') }} {{ $item->unit }}</td>
                                    <td class="num">{{ $formatRp($item->unit_price) }}</td>
                                    <td class="num"><strong>{{ $formatRp($item->subtotal) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="num">Total Estimasi RAB</td>
                                <td class="num">{{ $formatRp($workOrder->rab_total ?? 0) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        @if (! empty($approvals))
            <div class="gams-section">
                <h3>Status Approver</h3>
                <div class="gams-approver-list">
                    @foreach ($approvals as $row)
                        @php
                            $rowStatus = (string) ($row->status ?? 'pending');
                            $rowPillClass = $rowStatus === 'approved' ? 'gams-pill-ok' : ($rowStatus === 'rejected' ? 'gams-pill-err' : 'gams-pill-warn');
                            $rowStage = (string) ($row->stage ?? '');
                            $rowStageLabel = str_starts_with($rowStage, 'rab') ? 'RAB' : (str_starts_with($rowStage, 'sph') ? 'SPH' : 'Awal');
                        @endphp
                        <div class="gams-approver-row">
                            <div class="gams-approver-meta">
                                <strong>{{ $row->approver_name ?? '-' }}</strong>
                                <span class="gams-sub">
                                    {{ $row->approver_nik ? $row->approver_nik.' · ' : '' }}Tahap {{ $rowStageLabel }}
                                </span>
                                @if (! empty($row->comment))
                                    <p class="gams-approver-comment">"{{ $row->comment }}"</p>
                                @endif
                            </div>
                            <span class="gams-pill {{ $rowPillClass }}">{{ $approvalStatusLabels[$rowStatus] ?? $rowStatus }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($hasAttachments)
            <div class="gams-section">
                <h3>Lampiran Foto &amp; Dokumen</h3>
                @if (! empty($photoPaths))
                    <div class="gams-photos">
                        @foreach ($photoPaths as $index => $path)
                            <a class="gams-photo-link" href="{{ $attachmentBase }}/photo?index={{ $index }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $attachmentBase }}/photo?index={{ $index }}" alt="Foto Work Order {{ $index + 1 }}" />
                            </a>
                        @endforeach
                    </div>
                @endif
                @if (($workOrder->request_document_path ?? null) || ($workOrder->spl_document_path ?? null) || ($workOrder->sph_document_path ?? null))
                    <div class="gams-doc-links">
                        @if ($workOrder->request_document_path ?? null)
                            <a class="gams-doc-btn" href="{{ $attachmentBase }}/document" target="_blank" rel="noopener noreferrer">Buka Dokumen Pengajuan</a>
                        @endif
                        @if ($workOrder->spl_document_path ?? null)
                            <a class="gams-doc-btn" href="{{ $attachmentBase }}/spl" target="_blank" rel="noopener noreferrer">Buka Form SPL</a>
                        @endif
                        @if ($workOrder->sph_document_path ?? null)
                            <a class="gams-doc-btn" href="{{ $attachmentBase }}/sph" target="_blank" rel="noopener noreferrer">Buka Dokumen SPH</a>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        @if ($canAct)
            <form method="POST" action="{{ route('ga.wo.approval.decide', ['token' => $token]) }}" class="gams-decision-box">
                @csrf
                <p class="gams-greeting">
                    Halo <strong>{{ $approver->name ?? 'Approver' }}</strong>,
                    {{ $isRabStage
                        ? 'silakan tinjau rincian biaya RAB di atas dan berikan keputusan Anda:'
                        : ($isSphStage
                            ? 'silakan tinjau penawaran SPH vendor di atas dan berikan keputusan Anda:'
                            : 'silakan tinjau pengajuan Work Order di atas dan berikan keputusan Anda:') }}
                </p>
                <div>
                    <label class="gams-field-label" for="comment">
                        Catatan {{ $isRabStage ? '(opsional jika setuju, wajib jika minta revisi)' : '(opsional jika setuju, wajib jika menolak)' }}
                    </label>
                    <textarea
                        id="comment"
                        name="comment"
                        maxlength="2000"
                        class="gams-textarea"
                        placeholder="Catatan persetujuan (opsional) atau alasan penolakan / revisi (wajib jika menolak)..."
                    >{{ old('comment') }}</textarea>
                </div>
                <div class="gams-decision-actions">
                    <button
                        class="gams-btn gams-btn-reject"
                        type="submit"
                        name="decision"
                        value="reject"
                        onclick="return confirm('{{ $isRabStage ? 'Minta revisi RAB ini?' : 'Tolak pengajuan Work Order ini?' }}')"
                    >{{ $isRabStage ? 'Minta Revisi' : 'Tolak' }}</button>
                    <button
                        class="gams-btn gams-btn-approve"
                        type="submit"
                        name="decision"
                        value="approve"
                        onclick="return confirm('Setujui pengajuan ini?')"
                    >Setujui {{ $isSphStage ? 'SPH' : ($isRabStage ? 'RAB' : 'Work Order') }}</button>
                </div>
            </form>
        @else
            <div class="gams-done-note">
                {{ ($approval->status ?? '') === 'pending'
                    ? 'Work Order ini sudah tidak berada pada tahap persetujuan Anda.'
                    : 'Anda sudah memberikan keputusan pada tahap ini.' }}
                Halaman ini hanya menampilkan riwayat terbaru.
            </div>
        @endif
    </div>

    <footer class="gams-footer">
        Tautan ini bersifat rahasia dan hanya untuk approver yang ditunjuk. Jangan bagikan ke pihak lain.
    </footer>
</section>
@endsection
