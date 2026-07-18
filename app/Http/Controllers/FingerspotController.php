<?php

namespace App\Http\Controllers;

use App\Services\FingerspotAttendanceService;
use App\Services\FingerspotAttendanceWhatsAppNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class FingerspotController extends Controller
{
    public function __construct(
        private FingerspotAttendanceService $attendanceService,
        private FingerspotAttendanceWhatsAppNotifier $whatsAppNotifier
    )
    {
    }

    private function sendToFingerspot(string $endpoint, array $payload, bool $storeAttendance = false)
    {
        $url = rtrim(config('fingerspot.base_url', 'https://developer.fingerspot.io/api'), '/') . '/' . ltrim($endpoint, '/');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('fingerspot.api_token'),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])
        ->timeout(30)
        ->withBody(json_encode($payload), 'application/json')
        ->post($url);

        $responsePayload = $response->json();
        $storeResult = null;

        if ($storeAttendance && is_array($responsePayload)) {
            $storeResult = $this->attendanceService->storeFromApiResponse($responsePayload, $payload);
        }

        return response()->json([
            'status' => $response->successful(),
            'http_status' => $response->status(),
            'request_payload' => $payload,
            'response' => $responsePayload,
            'attendance_sync' => $storeResult,
            'raw_response' => $response->body(),
        ], $response->status());
    }

    private function transId(string $prefix): string
    {
        return $prefix . '-' . now()->format('YmdHis');
    }

    public function getAttlog(Request $request)
    {
        $request->validate([
            'cloud_id' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $result = $this->attendanceService->syncFromFingerspot(
                $request->start_date,
                $request->end_date,
                $request->cloud_id
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json($result, $result['http_status']);
    }

    public function getUserinfo(Request $request)
    {
        $request->validate([
            'cloud_id' => 'required|string',
            'pin' => 'required|string',
        ]);

        return $this->sendToFingerspot('get_userinfo', [
            'trans_id' => $this->transId('USERINFO'),
            'cloud_id' => $request->cloud_id,
            'pin' => $request->pin,
        ]);
    }

    public function setUserinfo(Request $request)
    {
        $request->validate([
            'cloud_id' => 'required|string',
            'pin' => 'required|string',
            'name' => 'required|string',
            'privilege' => 'nullable|string',
            'password' => 'nullable|string',
            'card' => 'nullable|string',
        ]);

        return $this->sendToFingerspot('set_userinfo', [
            'trans_id' => $this->transId('SETUSER'),
            'cloud_id' => $request->cloud_id,
            'pin' => $request->pin,
            'name' => $request->name,
            'privilege' => $request->privilege ?? '0',
            'password' => $request->password ?? '',
            'card' => $request->card ?? '',
        ]);
    }

    public function deleteUserinfo(Request $request)
    {
        $request->validate([
            'cloud_id' => 'required|string',
            'pin' => 'required|string',
        ]);

        return $this->sendToFingerspot('delete_userinfo', [
            'trans_id' => $this->transId('DELETEUSER'),
            'cloud_id' => $request->cloud_id,
            'pin' => $request->pin,
        ]);
    }

    public function getAllPin(Request $request)
    {
        $request->validate([
            'cloud_id' => 'required|string',
        ]);

        return $this->sendToFingerspot('get_all_pin', [
            'trans_id' => $this->transId('ALLPIN'),
            'cloud_id' => $request->cloud_id,
        ]);
    }

    public function setTime(Request $request)
    {
        $request->validate([
            'cloud_id' => 'required|string',
        ]);

        return $this->sendToFingerspot('set_time', [
            'trans_id' => $this->transId('SETTIME'),
            'cloud_id' => $request->cloud_id,
        ]);
    }

    public function registerOnline(Request $request)
    {
        $request->validate([
            'cloud_id' => 'required|string',
            'pin' => 'required|string',
            'verification' => 'required|string',
        ]);

        return $this->sendToFingerspot('reg_online', [
            'trans_id' => $this->transId('REGONLINE'),
            'cloud_id' => $request->cloud_id,
            'pin' => $request->pin,
            'verification' => $request->verification,
        ]);
    }

    public function restartMachine(Request $request)
    {
        $request->validate([
            'cloud_id' => 'required|string',
        ]);

        return $this->sendToFingerspot('restart', [
            'trans_id' => $this->transId('RESTART'),
            'cloud_id' => $request->cloud_id,
        ]);
    }

    public function getDevice(Request $request)
    {
        $request->validate([
            'cloud_id' => 'required|string',
        ]);

        return $this->sendToFingerspot('get_device', [
            'trans_id' => $this->transId('DEVICE'),
            'cloud_id' => $request->cloud_id,
        ]);
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();

        Log::info('FINGERSPOT WEBHOOK', $payload);

        Storage::append(
            'fingerspot-webhook.txt',
            now()->format('Y-m-d H:i:s') . ' | ' . json_encode($payload)
        );

        $webhookLog = $this->attendanceService->storeWebhookPayload(
            $payload,
            $request->ip(),
            $request->userAgent()
        );
        $storeResult = $this->attendanceService->storeFromWebhook($payload);
        $this->whatsAppNotifier->notify($webhookLog);

        return response()->json([
            'success' => true,
            'message' => 'Webhook received',
            'webhook_log_id' => $webhookLog->id,
            'attendance_sync' => $storeResult,
        ]);
    }
}
