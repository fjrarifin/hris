<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Log semua data masuk (WAJIB buat debug awal)
        Log::info('ATTENDANCE WEBHOOK HIT', [
            'ip' => $request->ip(),
            'data' => $request->all()
        ]);

        // contoh ambil data (sesuaikan nanti sama payload fingerspot)
        $pin = $request->input('pin');
        $scanTime = $request->input('scan_time');

        // dummy response dulu
        return response()->json([
            'status' => 'ok',
            'message' => 'Webhook received',
            'pin' => $pin,
            'scan_time' => $scanTime
        ]);
    }
}
