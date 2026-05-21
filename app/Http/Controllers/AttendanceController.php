<?php

namespace App\Http\Controllers;

use App\Services\FingerspotAttendanceService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AttendanceController extends Controller
{
    public function __construct(private FingerspotAttendanceService $attendanceService)
    {
    }

    public function pull(Request $request)
    {
        $data = $request->validate([
            'cloud_id' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        try {
            $result = $this->attendanceService->syncFromFingerspot(
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['cloud_id'] ?? null
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        \Log::info('FINGERSPOT RESPONSE', $result);

        return response()->json($result, $result['http_status']);
    }
}
