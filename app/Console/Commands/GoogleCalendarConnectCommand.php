<?php

namespace App\Console\Commands;

use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GoogleCalendarConnectCommand extends Command
{
    protected $signature = 'google-calendar:connect';

    protected $description = 'Generate one-time Google OAuth 2.0 authorization URL to connect Google Calendar';

    public function handle(GoogleCalendarService $calendar): int
    {
        $clientId = trim((string) config('services.google_calendar.client_id', ''));
        $clientSecret = trim((string) config('services.google_calendar.client_secret', ''));

        if ($clientId === '' || $clientSecret === '') {
            $this->error('GOOGLE_CALENDAR_CLIENT_ID dan GOOGLE_CALENDAR_CLIENT_SECRET wajib diisi di .env terlebih dahulu.');

            return self::FAILURE;
        }

        $state = Str::random(40);
        Cache::put('gcal_oauth_state_'.$state, true, now()->addMinutes(15));

        $redirectUri = $calendar->defaultRedirectUri();
        $url = $calendar->buildAuthorizationUrl($state, $redirectUri);

        $this->info('Pastikan Redirect URI berikut sudah terdaftar di Google Cloud Console:');
        $this->line("  {$redirectUri}");
        $this->newLine();
        $this->info('Buka URL berikut di browser dan login dengan akun Google Calendar yang diinginkan (berlaku 15 menit):');
        $this->newLine();
        $this->line($url);
        $this->newLine();

        return self::SUCCESS;
    }
}
