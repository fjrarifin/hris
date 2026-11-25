<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class GoogleCalendarOAuthController extends Controller
{
    public function callback(Request $request, GoogleCalendarService $calendar): Response
    {
        if ($request->filled('error')) {
            return response(
                '<h2>Otorisasi Google Calendar dibatalkan</h2><p>Error: '.e((string) $request->query('error')).'</p>',
                400,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }

        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if ($state === '' || $code === '' || ! Cache::pull('gcal_oauth_state_'.$state)) {
            return response(
                '<h2>State OAuth tidak valid atau sudah kedaluwarsa</h2><p>Silakan jalankan ulang perintah <code>php artisan google-calendar:connect</code>.</p>',
                400,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }

        $tokens = $calendar->exchangeAuthorizationCode($code);
        if (! $tokens || empty($tokens['refresh_token'])) {
            return response(
                '<h2>Gagal mendapatkan Refresh Token</h2><p>Pastikan Client ID, Client Secret, dan Redirect URI sudah sesuai, lalu coba ulangi.</p>',
                500,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }

        $refreshToken = e($tokens['refresh_token']);

        return response(
            <<<HTML
            <!DOCTYPE html>
            <html lang="id">
            <head><meta charset="utf-8"><title>Google Calendar Terhubung</title></head>
            <body style="font-family:system-ui,sans-serif;max-width:640px;margin:48px auto;padding:24px;line-height:1.6;">
                <h2 style="color:#059669;">Google Calendar Berhasil Terhubung!</h2>
                <p>Refresh token sudah disimpan otomatis di server (<code>storage/app/google-calendar-oauth.json</code>).</p>
                <p>Jika ingin menyimpannya di <code>.env</code> juga, salin baris berikut:</p>
                <pre style="background:#f3f4f6;padding:12px;border-radius:8px;overflow-x:auto;">GOOGLE_CALENDAR_REFRESH_TOKEN={$refreshToken}</pre>
                <p>Anda dapat menutup halaman ini dan mulai menjadwalkan interview di HRIS.</p>
            </body>
            </html>
            HTML,
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}
