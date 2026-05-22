<?php

namespace App\Http\Controllers;

use App\Services\FingerspotAttendanceService;
use App\Services\FingerspotAttendanceWhatsAppNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceWebhookController extends Controller
{
    public function __construct(
        private FingerspotAttendanceService $attendanceService,
        private FingerspotAttendanceWhatsAppNotifier $whatsAppNotifier
    )
    {
    }

    public function handle(Request $request)
    {
        // Log semua data masuk (WAJIB buat debug awal)
        Log::info('ATTENDANCE WEBHOOK HIT', [
            'ip' => $request->ip(),
            'data' => $request->all()
        ]);

        $webhookLog = $this->attendanceService->storeWebhookPayload(
            $request->all(),
            $request->ip(),
            $request->userAgent()
        );
        $storeResult = $this->attendanceService->storeFromWebhook($request->all());
        $this->whatsAppNotifier->notify($webhookLog);

        return response()->json([
            'status' => 'ok',
            'message' => 'Webhook received',
            'webhook_log_id' => $webhookLog->id,
            'attendance_sync' => $storeResult,
        ]);
    }
}
