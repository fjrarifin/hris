<?php

namespace App\Http\Controllers;

use App\Services\FingerspotAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceWebhookController extends Controller
{
    public function __construct(private FingerspotAttendanceService $attendanceService)
    {
    }

    public function handle(Request $request)
    {
        // Log semua data masuk (WAJIB buat debug awal)
        Log::info('ATTENDANCE WEBHOOK HIT', [
            'ip' => $request->ip(),
            'data' => $request->all()
        ]);

        $storeResult = $this->attendanceService->storeFromWebhook($request->all());

        return response()->json([
            'status' => 'ok',
            'message' => 'Webhook received',
            'attendance_sync' => $storeResult,
        ]);
    }
}
