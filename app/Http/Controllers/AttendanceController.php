<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Http;

class AttendanceController extends Controller
{
    public function pull()
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer XRRU7MBYK0KENQBO',
            'Content-Type' => 'application/json'
        ])->post('https://developer.fingerspot.io/api/get_attlog', [
            "trans_id"   => time(), // unique aja
            "cloud_id"   => "C262C4452315262F",
            // "start_date" => now()->subDays(2)->format('Y-m-d'),
            "start_date" => "2026-03-15",
            // "end_date"   => now()->format('Y-m-d'),
            "end_date"   => "2026-03-15",
            // "pin"       => "0147", // bisa juga array of pin kalau mau banyak sekaligus
        ]);

        \Log::info('FINGERSPOT RESPONSE', $response->json());
        dd($response->json());

        return response()->json([
            'status' => $response->status(),
            'data'   => $response->json()
        ]);
    }
}
