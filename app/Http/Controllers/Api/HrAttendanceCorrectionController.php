<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Services\IncompleteAttendanceWhatsAppReport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HrAttendanceCorrectionController extends Controller
{
    public function __construct(private readonly IncompleteAttendanceWhatsAppReport $report) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $date = Carbon::parse($validated['date'] ?? now()->subDay()->toDateString())->startOfDay();
        $keyword = strtolower(trim((string) ($validated['q'] ?? '')));
        $records = $this->report->recordsForDate($date, true)
            ->when($keyword !== '', function ($items) use ($keyword) {
                return $items->filter(fn (array $record): bool => collect([
                    $record['nik'],
                    $record['name'],
                    $record['position'],
                    $record['department'],
                ])->contains(fn ($value) => str_contains(strtolower((string) $value), $keyword)));
            })
            ->values();
        $perPage = 10;
        $page = max((int) ($validated['page'] ?? 1), 1);

        return response()->json([
            'date' => $date->toDateString(),
            'records' => $records->forPage($page, $perPage)->values(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $records->count(),
                'last_page' => max((int) ceil($records->count() / $perPage), 1),
            ],
        ]);
    }

    public function store(Request $request, string $nik): JsonResponse
    {
        $validated = $request->validate([
            'attendance_date' => ['required', 'date'],
            'corrected_scan_in' => ['nullable', 'date_format:H:i'],
            'corrected_scan_out' => ['nullable', 'date_format:H:i'],
            'has_missing_attendance_form' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $date = Carbon::parse($validated['attendance_date'])->startOfDay();
        $record = $this->report->recordsForDate($date, true)->firstWhere('nik', $nik);

        if (! $record) {
            throw ValidationException::withMessages([
                'attendance_date' => ['Data scan parsial karyawan pada tanggal ini tidak ditemukan.'],
            ]);
        }

        if (! $record['raw_scan_in'] && empty($validated['corrected_scan_in'])) {
            throw ValidationException::withMessages([
                'corrected_scan_in' => ['Jam masuk koreksi wajib diisi.'],
            ]);
        }

        if (! $record['raw_scan_out'] && empty($validated['corrected_scan_out'])) {
            throw ValidationException::withMessages([
                'corrected_scan_out' => ['Jam pulang koreksi wajib diisi.'],
            ]);
        }

        AttendanceCorrection::query()->updateOrCreate(
            ['nik' => $nik, 'attendance_date' => $date->toDateString()],
            [
                'corrected_scan_in' => $record['raw_scan_in'] ? null : $validated['corrected_scan_in'],
                'corrected_scan_out' => $record['raw_scan_out'] ? null : $validated['corrected_scan_out'],
                'has_missing_attendance_form' => ($validated['has_missing_attendance_form'] ?? false) ? true : null,
                'notes' => $validated['notes'] ?? null,
                'corrected_by' => $request->user()->id,
            ]
        );

        return response()->json([
            'message' => 'Koreksi absensi berhasil disimpan.',
            'data' => $this->report->recordsForDate($date, true)->firstWhere('nik', $nik),
        ]);
    }
}
