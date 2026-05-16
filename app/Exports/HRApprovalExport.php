<?php

namespace App\Exports;

use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class HRApprovalExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private string $type)
    {
    }

    public function collection(): Collection
    {
        return match ($this->type) {
            'leave' => $this->leaveRows(),
            'ph' => $this->phRows(),
            default => collect(),
        };
    }

    public function headings(): array
    {
        return match ($this->type) {
            'leave' => [
                'ID',
                'NIK',
                'Nama',
                'Jenis Cuti',
                'Tanggal Mulai',
                'Tanggal Selesai',
                'Keterangan',
                'Status',
                'Disetujui Atasan Langsung',
                'Disetujui HR',
                'Alasan Penolakan',
                'Tanggal Pengajuan',
            ],
            'ph' => [
                'ID',
                'NIK',
                'Nama',
                'Hari Libur',
                'Tanggal PH',
                'Tanggal Pengambilan',
                'Status',
                'Disetujui Atasan',
                'Disetujui HR',
                'Alasan Penolakan',
                'Tanggal Pengajuan',
            ],
            default => [],
        };
    }

    private function leaveRows(): Collection
    {
        return LeaveRequest::with('user')
            ->whereNotNull('manager_approved_at')
            ->latest()
            ->get()
            ->map(fn($request) => [
                $request->id,
                $request->user?->username,
                $request->user?->name,
                LeaveRequest::LEAVE_TYPES[$request->leave_type] ?? $request->leave_type,
                $this->formatDate($request->start_date),
                $this->formatDate($request->end_date),
                $request->reason,
                $this->statusLabel($request),
                $this->formatDateTime($request->manager_approved_at),
                $this->formatDateTime($request->hr_approved_at),
                $request->reject_reason,
                $this->formatDateTime($request->created_at),
            ]);
    }

    private function phRows(): Collection
    {
        return PublicHolidayRequest::with(['user', 'holiday'])
            ->whereNotNull('manager_approved_at')
            ->latest()
            ->get()
            ->map(fn($request) => [
                $request->id,
                $request->user?->username,
                $request->user?->name,
                $request->holiday?->name,
                $this->formatDate($request->holiday?->holiday_date),
                $this->formatDate($request->claim_date),
                $this->statusLabel($request),
                $this->formatDateTime($request->manager_approved_at),
                $this->formatDateTime($request->hr_approved_at),
                $request->reject_reason,
                $this->formatDateTime($request->created_at),
            ]);
    }

    private function statusLabel($request): string
    {
        if ($request->status === 'rejected') {
            return 'Ditolak';
        }

        if ($request->status === 'cancelled') {
            return 'Dibatalkan';
        }

        if ($request->hr_approved_at) {
            return 'Disetujui HR';
        }

        return 'Menunggu HR';
    }

    private function formatDate($value): ?string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d') : null;
    }

    private function formatDateTime($value): ?string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : null;
    }
}
