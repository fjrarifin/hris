<?php

namespace App\Services;

use App\Models\AttendanceCorrection;
use App\Models\EmployeeExtraOff;
use App\Models\ExtraOffRequest;
use App\Models\FingerspotAttendanceLog;
use App\Models\PublicHolidayRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendedDayOffCancellationService
{
    /** Reconcile all past claims so late attendance imports are not missed. */
    public function reconcile(?int $userId = null, ?Carbon $date = null, ?callable $onCancelled = null): int
    {
        $cancelled = 0;

        foreach ([PublicHolidayRequest::class, ExtraOffRequest::class] as $model) {
            $model::query()
                ->with('user.karyawan')
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('claim_date', '<=', now()->toDateString())
                ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
                ->when($date, fn ($query) => $query->whereDate('claim_date', $date))
                ->chunkById(200, function ($requests) use (&$cancelled, $onCancelled): void {
                    foreach ($requests as $request) {
                        $employee = $request->user?->karyawan;
                        if (blank($employee?->pin)) {
                            continue;
                        }

                        if (AttendanceCorrection::query()
                            ->where('nik', $employee->nik)
                            ->whereDate('attendance_date', $request->claim_date)
                            ->where('correction_type', 'day_off')
                            ->exists()) {
                            continue;
                        }

                        if (! FingerspotAttendanceLog::query()
                            ->where('pin', $employee->pin)
                            ->whereDate('scan_date', $request->claim_date)
                            ->exists()) {
                            continue;
                        }

                        // Cancellation and replacement credit must commit together.
                        $updated = DB::transaction(function () use ($request, $employee) {
                            $locked = $request->newQuery()->lockForUpdate()->find($request->id);
                            if (! $locked || ! in_array($locked->status, ['pending', 'approved'], true)) {
                                return 0;
                            }
                            $locked->update([
                                'status' => 'cancelled',
                                'reject_reason' => 'Dibatalkan otomatis karena karyawan hadir/scan masuk pada tanggal pengambilan.',
                            ]);
                            if ($locked instanceof PublicHolidayRequest) {
                                $holiday = $locked->holiday;
                                $expiry = $holiday?->holiday_date?->copy()->addDays(90)->endOfDay();
                                // Only a claim scheduled within its original validity earns replacement.
                                if ($expiry && now()->gt($expiry) && $locked->claim_date->lte($expiry)
                                    && $locked->claim_date->gte($holiday->holiday_date)) {
                                    $end = now()->startOfMonth()->day(24);
                                    if (now()->day > 24) {
                                        $end->addMonthNoOverflow();
                                    }
                                    EmployeeExtraOff::firstOrCreate([
                                        'public_holiday_request_id' => $locked->id,
                                    ], [
                                        'karyawan_nik' => $employee->nik,
                                        'periode_start' => $end->copy()->subMonthNoOverflow()->day(25)->toDateString(),
                                        'periode_end' => $end->toDateString(),
                                        'expires_at' => $end->toDateString(),
                                        'days' => 1,
                                        'source' => 'ph_conversion',
                                        'notes' => 'Pengganti PH '.$holiday->name.'; pengajuan #'.$locked->id.' dibatalkan karena hadir.',
                                    ]);
                                }
                            }

                            return 1;
                        });
                        $cancelled += $updated;
                        if ($updated && $onCancelled) {
                            $onCancelled($request->fresh(), $employee);
                        }
                    }
                });
        }

        return $cancelled;
    }
}
